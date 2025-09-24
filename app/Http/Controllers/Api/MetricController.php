<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\Metric;
use App\Services\InfluxDBService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class MetricController extends Controller
{
    public function __construct(
        private InfluxDBService $influxdb
    ) {}

    public function ingest(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'agent_token' => 'required|string|exists:agents,api_token',
            'timestamp' => 'required|date',
            'measurement' => 'required|string',
            'field_key' => 'required|string',
            'field_value' => 'required|numeric',
            'tags' => 'nullable|array',
            'metadata' => 'nullable|array'
        ]);

        $agent = Agent::where('api_token', $validated['agent_token'])->first();

        try {
            DB::beginTransaction();

            // Create metric entry in database
            $metric = Metric::create([
                'agent_id' => $agent->id,
                'timestamp' => $validated['timestamp'],
                'measurement' => $validated['measurement'],
                'field_key' => $validated['field_key'],
                'field_value' => $validated['field_value'],
                'tags' => $validated['tags'],
                'environment' => $agent->environment,
                'metadata' => $validated['metadata']
            ]);

            // Write to InfluxDB
            $influxTimestamp = $this->influxdb->writeMetric($metric);
            $metric->update(['influxdb_timestamp' => $influxTimestamp]);

            DB::commit();

            return response()->json([
                'success' => true,
                'metric_id' => $metric->id,
                'influxdb_timestamp' => $influxTimestamp,
                'message' => 'Metric ingested successfully'
            ], 201);

        } catch (\Exception $e) {
            DB::rollback();

            return response()->json([
                'success' => false,
                'error' => 'Failed to ingest metric: ' . $e->getMessage()
            ], 500);
        }
    }

    public function batchIngest(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'agent_token' => 'required|string|exists:agents,api_token',
            'metrics' => 'required|array|min:1|max:5000',
            'metrics.*.timestamp' => 'required|date',
            'metrics.*.measurement' => 'required|string',
            'metrics.*.field_key' => 'required|string',
            'metrics.*.field_value' => 'required|numeric',
            'metrics.*.tags' => 'nullable|array',
            'metrics.*.metadata' => 'nullable|array'
        ]);

        $agent = Agent::where('api_token', $validated['agent_token'])->first();
        $processed = 0;
        $errors = [];

        try {
            DB::beginTransaction();

            $metrics = [];
            foreach ($validated['metrics'] as $index => $metricData) {
                try {
                    $metric = Metric::create([
                        'agent_id' => $agent->id,
                        'timestamp' => $metricData['timestamp'],
                        'measurement' => $metricData['measurement'],
                        'field_key' => $metricData['field_key'],
                        'field_value' => $metricData['field_value'],
                        'tags' => $metricData['tags'] ?? null,
                        'environment' => $agent->environment,
                        'metadata' => $metricData['metadata'] ?? null
                    ]);

                    $metrics[] = $metric;
                    $processed++;

                } catch (\Exception $e) {
                    $errors[] = [
                        'index' => $index,
                        'error' => $e->getMessage()
                    ];
                }
            }

            // Bulk write to InfluxDB
            if (!empty($metrics)) {
                $this->influxdb->bulkWriteMetrics($metrics);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'processed' => $processed,
                'total' => count($validated['metrics']),
                'errors' => $errors,
                'message' => "Processed {$processed} metrics successfully"
            ], 201);

        } catch (\Exception $e) {
            DB::rollback();

            return response()->json([
                'success' => false,
                'processed' => $processed,
                'error' => 'Batch ingestion failed: ' . $e->getMessage(),
                'errors' => $errors
            ], 500);
        }
    }
}