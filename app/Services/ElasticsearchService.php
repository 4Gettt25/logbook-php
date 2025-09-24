<?php

namespace App\Services;

use App\Models\Log;
use Elasticsearch\Client;
use Elasticsearch\ClientBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ElasticsearchService
{
    private Client $client;
    private string $indexPrefix;

    public function __construct()
    {
        $this->client = ClientBuilder::create()
            ->setHosts([
                [
                    'host' => config('services.elasticsearch.host', 'localhost'),
                    'port' => config('services.elasticsearch.port', 9200),
                    'scheme' => config('services.elasticsearch.scheme', 'http'),
                    'user' => config('services.elasticsearch.username'),
                    'pass' => config('services.elasticsearch.password'),
                ]
            ])
            ->build();

        $this->indexPrefix = config('services.elasticsearch.index_prefix', 'logbook');
    }

    public function indexLog(Log $log): string
    {
        $index = $this->getIndexName($log->timestamp);
        
        $document = [
            'id' => $log->id,
            'agent_id' => $log->agent_id,
            'agent_name' => $log->agent->name,
            'timestamp' => $log->timestamp->toISOString(),
            'level' => $log->level,
            'message' => $log->message,
            'source' => $log->source,
            'facility' => $log->facility,
            'hostname' => $log->hostname,
            'process_name' => $log->process_name,
            'process_id' => $log->process_id,
            'environment' => $log->environment,
            'tags' => $log->tags ?? [],
            'metadata' => $log->metadata ?? [],
            'created_at' => $log->created_at->toISOString()
        ];

        $params = [
            'index' => $index,
            'body' => $document
        ];

        $response = $this->client->index($params);
        
        return $response['_id'];
    }

    public function bulkIndexLogs(Collection $logs): array
    {
        $body = [];
        
        foreach ($logs as $log) {
            $index = $this->getIndexName($log->timestamp);
            
            $body[] = [
                'index' => [
                    '_index' => $index
                ]
            ];

            $body[] = [
                'id' => $log->id,
                'agent_id' => $log->agent_id,
                'agent_name' => $log->agent->name,
                'timestamp' => $log->timestamp->toISOString(),
                'level' => $log->level,
                'message' => $log->message,
                'source' => $log->source,
                'facility' => $log->facility,
                'hostname' => $log->hostname,
                'process_name' => $log->process_name,
                'process_id' => $log->process_id,
                'environment' => $log->environment,
                'tags' => $log->tags ?? [],
                'metadata' => $log->metadata ?? [],
                'created_at' => $log->created_at->toISOString()
            ];
        }

        $params = [
            'body' => $body
        ];

        return $this->client->bulk($params);
    }

    public function searchLogs(array $filters = [], int $size = 50, int $from = 0): array
    {
        $query = [
            'bool' => [
                'must' => []
            ]
        ];

        // Add filters
        if (!empty($filters['environment'])) {
            $query['bool']['must'][] = [
                'term' => ['environment' => $filters['environment']]
            ];
        }

        if (!empty($filters['level'])) {
            $query['bool']['must'][] = [
                'term' => ['level' => $filters['level']]
            ];
        }

        if (!empty($filters['source'])) {
            $query['bool']['must'][] = [
                'term' => ['source' => $filters['source']]
            ];
        }

        if (!empty($filters['hostname'])) {
            $query['bool']['must'][] = [
                'term' => ['hostname' => $filters['hostname']]
            ];
        }

        if (!empty($filters['agent_id'])) {
            $query['bool']['must'][] = [
                'term' => ['agent_id' => $filters['agent_id']]
            ];
        }

        if (!empty($filters['message'])) {
            $query['bool']['must'][] = [
                'match' => ['message' => $filters['message']]
            ];
        }

        if (!empty($filters['from_date']) || !empty($filters['to_date'])) {
            $range = [];
            if (!empty($filters['from_date'])) {
                $range['gte'] = Carbon::parse($filters['from_date'])->toISOString();
            }
            if (!empty($filters['to_date'])) {
                $range['lte'] = Carbon::parse($filters['to_date'])->toISOString();
            }
            
            $query['bool']['must'][] = [
                'range' => ['timestamp' => $range]
            ];
        }

        $params = [
            'index' => $this->indexPrefix . '-logs-*',
            'body' => [
                'query' => $query,
                'sort' => [
                    ['timestamp' => ['order' => 'desc']]
                ],
                'size' => $size,
                'from' => $from
            ]
        ];

        return $this->client->search($params);
    }

    public function getLogStats(array $filters = []): array
    {
        $query = [
            'bool' => [
                'must' => []
            ]
        ];

        // Apply same filters as search
        if (!empty($filters['environment'])) {
            $query['bool']['must'][] = [
                'term' => ['environment' => $filters['environment']]
            ];
        }

        $params = [
            'index' => $this->indexPrefix . '-logs-*',
            'body' => [
                'query' => $query,
                'aggs' => [
                    'levels' => [
                        'terms' => ['field' => 'level']
                    ],
                    'sources' => [
                        'terms' => ['field' => 'source']
                    ],
                    'environments' => [
                        'terms' => ['field' => 'environment']
                    ],
                    'timeline' => [
                        'date_histogram' => [
                            'field' => 'timestamp',
                            'calendar_interval' => '1h'
                        ]
                    ]
                ]
            ]
        ];

        return $this->client->search($params);
    }

    private function getIndexName(Carbon $timestamp): string
    {
        return $this->indexPrefix . '-logs-' . $timestamp->format('Y.m.d');
    }

    public function ensureIndexTemplate(): void
    {
        $template = [
            'index_patterns' => [$this->indexPrefix . '-logs-*'],
            'template' => [
                'settings' => [
                    'number_of_shards' => 1,
                    'number_of_replicas' => 0,
                    'index.refresh_interval' => '30s'
                ],
                'mappings' => [
                    'properties' => [
                        'id' => ['type' => 'long'],
                        'agent_id' => ['type' => 'long'],
                        'agent_name' => ['type' => 'keyword'],
                        'timestamp' => ['type' => 'date'],
                        'level' => ['type' => 'keyword'],
                        'message' => ['type' => 'text'],
                        'source' => ['type' => 'keyword'],
                        'facility' => ['type' => 'keyword'],
                        'hostname' => ['type' => 'keyword'],
                        'process_name' => ['type' => 'keyword'],
                        'process_id' => ['type' => 'integer'],
                        'environment' => ['type' => 'keyword'],
                        'tags' => ['type' => 'object'],
                        'metadata' => ['type' => 'object'],
                        'created_at' => ['type' => 'date']
                    ]
                ]
            ]
        ];

        $this->client->indices()->putIndexTemplate([
            'name' => $this->indexPrefix . '-logs-template',
            'body' => $template
        ]);
    }
}