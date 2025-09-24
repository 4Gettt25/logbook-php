<?php

namespace App\Services;

use App\Models\Metric;
use InfluxDB2\Client;
use InfluxDB2\Model\WritePrecision;
use InfluxDB2\Point;
use Illuminate\Support\Collection;

class InfluxDBService
{
    private Client $client;
    private string $org;
    private string $bucket;

    public function __construct()
    {
        $this->client = new Client([
            "url" => config('services.influxdb.url', 'http://localhost:8086'),
            "token" => config('services.influxdb.token'),
            "bucket" => config('services.influxdb.bucket', 'metrics'),
            "org" => config('services.influxdb.org', 'logbook'),
        ]);

        $this->org = config('services.influxdb.org', 'logbook');
        $this->bucket = config('services.influxdb.bucket', 'metrics');
    }

    public function writeMetric(Metric $metric): int
    {
        $writeApi = $this->client->createWriteApi();
        
        $point = Point::measurement($metric->measurement)
            ->addTag('agent_id', (string) $metric->agent_id)
            ->addTag('agent_name', $metric->agent->name)
            ->addTag('environment', $metric->environment)
            ->addTag('hostname', $metric->agent->hostname);

        // Add custom tags
        if ($metric->tags) {
            foreach ($metric->tags as $key => $value) {
                $point->addTag($key, (string) $value);
            }
        }

        $point->addField($metric->field_key, $metric->field_value)
              ->time($metric->timestamp->getTimestamp(), WritePrecision::S);

        $writeApi->write($point);
        $writeApi->close();

        return $metric->timestamp->getTimestamp();
    }

    public function bulkWriteMetrics(Collection $metrics): array
    {
        $writeApi = $this->client->createWriteApi();
        $points = [];

        foreach ($metrics as $metric) {
            $point = Point::measurement($metric->measurement)
                ->addTag('agent_id', (string) $metric->agent_id)
                ->addTag('agent_name', $metric->agent->name)
                ->addTag('environment', $metric->environment)
                ->addTag('hostname', $metric->agent->hostname);

            // Add custom tags
            if ($metric->tags) {
                foreach ($metric->tags as $key => $value) {
                    $point->addTag($key, (string) $value);
                }
            }

            $point->addField($metric->field_key, $metric->field_value)
                  ->time($metric->timestamp->getTimestamp(), WritePrecision::S);

            $points[] = $point;
        }

        $writeApi->write($points);
        $writeApi->close();

        return ['written' => count($points)];
    }

    public function queryMetrics(string $measurement, array $filters = [], string $timeRange = '-1h'): array
    {
        $queryApi = $this->client->createQueryApi();

        $query = 'from(bucket: "' . $this->bucket . '")
            |> range(start: ' . $timeRange . ')
            |> filter(fn: (r) => r._measurement == "' . $measurement . '")';

        // Add filters
        if (!empty($filters['agent_id'])) {
            $query .= ' |> filter(fn: (r) => r.agent_id == "' . $filters['agent_id'] . '")';
        }

        if (!empty($filters['environment'])) {
            $query .= ' |> filter(fn: (r) => r.environment == "' . $filters['environment'] . '")';
        }

        if (!empty($filters['hostname'])) {
            $query .= ' |> filter(fn: (r) => r.hostname == "' . $filters['hostname'] . '")';
        }

        if (!empty($filters['field_key'])) {
            $query .= ' |> filter(fn: (r) => r._field == "' . $filters['field_key'] . '")';
        }

        $query .= ' |> sort(columns: ["_time"], desc: true)';

        $result = $queryApi->query($query);
        $data = [];

        foreach ($result as $table) {
            foreach ($table->records as $record) {
                $data[] = [
                    'time' => $record->getTime(),
                    'value' => $record->getValue(),
                    'field' => $record->getField(),
                    'measurement' => $record->getMeasurement(),
                    'tags' => $record->getValues()
                ];
            }
        }

        return $data;
    }

    public function getMetricStats(array $filters = [], string $timeRange = '-24h'): array
    {
        $queryApi = $this->client->createQueryApi();

        $query = 'from(bucket: "' . $this->bucket . '")
            |> range(start: ' . $timeRange . ')';

        // Add filters
        if (!empty($filters['environment'])) {
            $query .= ' |> filter(fn: (r) => r.environment == "' . $filters['environment'] . '")';
        }

        if (!empty($filters['agent_id'])) {
            $query .= ' |> filter(fn: (r) => r.agent_id == "' . $filters['agent_id'] . '")';
        }

        // Group by measurement and get stats
        $query .= '
            |> group(columns: ["_measurement"])
            |> aggregateWindow(every: 1h, fn: mean, createEmpty: false)
            |> yield(name: "mean")';

        $result = $queryApi->query($query);
        $stats = [];

        foreach ($result as $table) {
            $measurement = null;
            $values = [];
            
            foreach ($table->records as $record) {
                $measurement = $record->getMeasurement();
                $values[] = [
                    'time' => $record->getTime(),
                    'value' => $record->getValue(),
                    'field' => $record->getField()
                ];
            }

            if ($measurement) {
                $stats[$measurement] = $values;
            }
        }

        return $stats;
    }

    public function getRecentMetrics(int $agentId, string $timeRange = '-1h'): array
    {
        $queryApi = $this->client->createQueryApi();

        $query = 'from(bucket: "' . $this->bucket . '")
            |> range(start: ' . $timeRange . ')
            |> filter(fn: (r) => r.agent_id == "' . $agentId . '")
            |> group(columns: ["_measurement", "_field"])
            |> last()';

        $result = $queryApi->query($query);
        $metrics = [];

        foreach ($result as $table) {
            foreach ($table->records as $record) {
                $measurement = $record->getMeasurement();
                $field = $record->getField();
                
                if (!isset($metrics[$measurement])) {
                    $metrics[$measurement] = [];
                }
                
                $metrics[$measurement][$field] = [
                    'value' => $record->getValue(),
                    'time' => $record->getTime(),
                    'tags' => array_filter($record->getValues(), function($key) {
                        return !in_array($key, ['_time', '_value', '_field', '_measurement', 'result', 'table']);
                    }, ARRAY_FILTER_USE_KEY)
                ];
            }
        }

        return $metrics;
    }

    public function createBucket(): void
    {
        $bucketsApi = $this->client->createBucketsApi();
        
        try {
            $bucket = $bucketsApi->createBucket([
                'name' => $this->bucket,
                'orgID' => $this->getOrgId(),
                'retentionRules' => [
                    [
                        'type' => 'expire',
                        'everySeconds' => 2592000 // 30 days
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            // Bucket might already exist
        }
    }

    private function getOrgId(): string
    {
        $orgsApi = $this->client->createOrganizationsApi();
        $orgs = $orgsApi->findOrganizations();
        
        foreach ($orgs as $org) {
            if ($org->getName() === $this->org) {
                return $org->getId();
            }
        }
        
        throw new \Exception("Organization '{$this->org}' not found");
    }
}