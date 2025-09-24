<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\Log;
use App\Services\ElasticsearchService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class LogController extends Controller
{
    public function __construct(
        private ElasticsearchService $elasticsearch
    ) {}

    public function ingest(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'agent_token' => 'required|string|exists:agents,api_token',
            'timestamp' => 'required|date',
            'level' => 'required|string|in:emergency,alert,critical,error,warning,notice,info,debug',
            'message' => 'required|string',
            'source' => 'required|string|in:syslog,journald,nginx,apache,application,custom',
            'facility' => 'nullable|string',
            'hostname' => 'required|string',
            'process_name' => 'nullable|string',
            'process_id' => 'nullable|integer',
            'tags' => 'nullable|array',
            'metadata' => 'nullable|array'
        ]);

        $agent = Agent::where('api_token', $validated['agent_token'])->first();

        try {
            DB::beginTransaction();

            // Create log entry in database
            $log = Log::create([
                'agent_id' => $agent->id,
                'timestamp' => $validated['timestamp'],
                'level' => $validated['level'],
                'message' => $validated['message'],
                'source' => $validated['source'],
                'facility' => $validated['facility'],
                'hostname' => $validated['hostname'],
                'process_name' => $validated['process_name'],
                'process_id' => $validated['process_id'],
                'environment' => $agent->environment,
                'tags' => $validated['tags'],
                'metadata' => $validated['metadata']
            ]);

            // Index in Elasticsearch
            $elasticsearchId = $this->elasticsearch->indexLog($log);
            $log->update(['elasticsearch_id' => $elasticsearchId]);

            DB::commit();

            return response()->json([
                'success' => true,
                'log_id' => $log->id,
                'elasticsearch_id' => $elasticsearchId,
                'message' => 'Log ingested successfully'
            ], 201);

        } catch (\Exception $e) {
            DB::rollback();

            return response()->json([
                'success' => false,
                'error' => 'Failed to ingest log: ' . $e->getMessage()
            ], 500);
        }
    }

    public function batchIngest(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'agent_token' => 'required|string|exists:agents,api_token',
            'logs' => 'required|array|min:1|max:1000',
            'logs.*.timestamp' => 'required|date',
            'logs.*.level' => 'required|string|in:emergency,alert,critical,error,warning,notice,info,debug',
            'logs.*.message' => 'required|string',
            'logs.*.source' => 'required|string|in:syslog,journald,nginx,apache,application,custom',
            'logs.*.facility' => 'nullable|string',
            'logs.*.hostname' => 'required|string',
            'logs.*.process_name' => 'nullable|string',
            'logs.*.process_id' => 'nullable|integer',
            'logs.*.tags' => 'nullable|array',
            'logs.*.metadata' => 'nullable|array'
        ]);

        $agent = Agent::where('api_token', $validated['agent_token'])->first();
        $processed = 0;
        $errors = [];

        try {
            DB::beginTransaction();

            $logs = [];
            foreach ($validated['logs'] as $index => $logData) {
                try {
                    $log = Log::create([
                        'agent_id' => $agent->id,
                        'timestamp' => $logData['timestamp'],
                        'level' => $logData['level'],
                        'message' => $logData['message'],
                        'source' => $logData['source'],
                        'facility' => $logData['facility'] ?? null,
                        'hostname' => $logData['hostname'],
                        'process_name' => $logData['process_name'] ?? null,
                        'process_id' => $logData['process_id'] ?? null,
                        'environment' => $agent->environment,
                        'tags' => $logData['tags'] ?? null,
                        'metadata' => $logData['metadata'] ?? null
                    ]);

                    $logs[] = $log;
                    $processed++;

                } catch (\Exception $e) {
                    $errors[] = [
                        'index' => $index,
                        'error' => $e->getMessage()
                    ];
                }
            }

            // Bulk index in Elasticsearch
            if (!empty($logs)) {
                $this->elasticsearch->bulkIndexLogs($logs);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'processed' => $processed,
                'total' => count($validated['logs']),
                'errors' => $errors,
                'message' => "Processed {$processed} logs successfully"
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