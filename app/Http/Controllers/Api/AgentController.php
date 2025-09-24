<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AgentController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'hostname' => 'required|string|max:255',
            'ip_address' => 'required|ip',
            'environment' => 'required|string|in:development,staging,production',
            'version' => 'nullable|string|max:50',
            'os_info' => 'nullable|array',
            'architecture' => 'nullable|string|max:50',
            'metadata' => 'nullable|array'
        ]);

        // Check if agent already exists
        $existingAgent = Agent::where('hostname', $validated['hostname'])
            ->where('environment', $validated['environment'])
            ->first();

        if ($existingAgent) {
            // Update existing agent
            $existingAgent->update([
                'name' => $validated['name'],
                'ip_address' => $validated['ip_address'],
                'version' => $validated['version'] ?? $existingAgent->version,
                'os_info' => $validated['os_info'] ?? $existingAgent->os_info,
                'architecture' => $validated['architecture'] ?? $existingAgent->architecture,
                'metadata' => $validated['metadata'] ?? $existingAgent->metadata,
                'status' => Agent::STATUS_ACTIVE,
                'last_heartbeat' => now()
            ]);

            return response()->json([
                'success' => true,
                'agent_id' => $existingAgent->id,
                'api_token' => $existingAgent->api_token,
                'message' => 'Agent updated successfully'
            ]);
        }

        // Create new agent
        $agent = Agent::create([
            ...$validated,
            'api_token' => Str::random(64),
            'status' => Agent::STATUS_ACTIVE,
            'last_heartbeat' => now()
        ]);

        return response()->json([
            'success' => true,
            'agent_id' => $agent->id,
            'api_token' => $agent->api_token,
            'message' => 'Agent registered successfully'
        ], 201);
    }

    public function heartbeat(Request $request, Agent $agent): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'nullable|string|in:active,inactive,error',
            'metadata' => 'nullable|array'
        ]);

        $agent->update([
            'status' => $validated['status'] ?? Agent::STATUS_ACTIVE,
            'last_heartbeat' => now(),
            'metadata' => array_merge($agent->metadata ?? [], $validated['metadata'] ?? [])
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Heartbeat received',
            'server_time' => now()->toISOString()
        ]);
    }

    public function getConfig(Request $request, Agent $agent): JsonResponse
    {
        $config = [
            'elasticsearch' => [
                'host' => config('services.elasticsearch.host'),
                'port' => config('services.elasticsearch.port'),
                'index_prefix' => config('services.elasticsearch.index_prefix')
            ],
            'influxdb' => [
                'url' => config('services.influxdb.url'),
                'org' => config('services.influxdb.org'),
                'bucket' => config('services.influxdb.bucket')
            ],
            'telegraf' => [
                'api_port' => config('services.telegraf.api_port'),
                'tls_cert_path' => config('services.telegraf.tls_cert_path'),
                'tls_key_path' => config('services.telegraf.tls_key_path')
            ],
            'log_sources' => [
                'syslog' => true,
                'journald' => true,
                'nginx' => true,
                'apache' => true
            ],
            'metric_collection' => [
                'cpu' => true,
                'memory' => true,
                'disk' => true,
                'network' => true,
                'load' => true,
                'processes' => true
            ]
        ];

        // Merge with agent-specific config
        if ($agent->telegraf_config) {
            $config = array_merge_recursive($config, $agent->telegraf_config);
        }

        return response()->json([
            'success' => true,
            'config' => $config,
            'agent' => [
                'id' => $agent->id,
                'name' => $agent->name,
                'environment' => $agent->environment
            ]
        ]);
    }
}