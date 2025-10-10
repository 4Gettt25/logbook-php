Agents Guide
This document explains how to deploy, manage, and operate Telegraf agents that forward logs and metrics from distributed servers to the central log management system.
---
1. Overview
Agents are lightweight Telegraf instances installed on each server. They collect:
- Logs: journald, syslog, Nginx, Apache, application-specific files.
- Metrics: CPU, memory, disk, network, process counts, container stats.
- Service telemetry: Nginx/Apache status, PHP-FPM, Postgres, Redis (optional).
Agents forward this data securely over TLS to:
- Elasticsearch → full-text searchable log events.
- InfluxDB → time-series metrics and counters.
- Laravel API → agent enrollment, configuration delivery, heartbeats.
2. Lifecycle
Enrollment
1. Add a new agent in the Filament UI.
2. Backend generates:
   - Elasticsearch API key (logs ingest only).
   - InfluxDB token (bucket write only).
   - One-time enrollment token.
3. UI provides a bootstrap script for the target server.
Installation
On the server:
curl -sSL https://your-app/api/agents/<uuid>/install.sh | sudo bash
This script installs Telegraf, downloads a config tailored to the agent, and starts the service.
Operation
Agent collects logs/metrics at regular intervals.
Sends logs to Elasticsearch, metrics to InfluxDB.
Posts a heartbeat to the backend every minute.
Backend marks agents green/yellow/red based on last heartbeat.
Decommissioning
Revoke ES API key and Influx token in the UI.
Stop and remove Telegraf on the server.
Agent marked inactive in the backend.
3. Configuration
The backend serves a rendered telegraf.conf template with variables:
${AGENT_ID} → UUID assigned in the UI.
${ES_API_KEY} → scoped ES API key.
${INFLUX_TOKEN} → scoped Influx token.
${ENV} → environment tag (e.g., prod/staging).
${ROLE} → server role tag (web, db, cache).
${APP_HOST} → Laravel backend URL.
Example config includes:
inputs.journald for system logs.
inputs.tail for Nginx/Apache access and error logs.
inputs.cpu, inputs.mem, inputs.disk, inputs.net, inputs.system.
outputs.elasticsearch and outputs.influxdb_v2.
4. Logs Collected
System logs: authentication events, kernel messages, services.
Nginx/Apache access: prefer JSON logs for parsing; otherwise grok patterns.
Nginx/Apache error: stack traces and critical service failures.
App logs: Laravel JSON logs if configured.
Optional: database slow queries, Redis stats, Docker container logs.
5. Security
All communication is encrypted with TLS.
Per-agent credentials:
ES API key → write-only to logs-*.
InfluxDB token → write-only to logs_raw bucket.
Enrollment tokens expire after use.
Revoked agents cannot push new data.
6. Heartbeats
Agents post a heartbeat to the backend via outputs.http:
{
  "agent_id": "uuid",
  "hostname": "server1",
  "version": "1.31",
  "status": "ok"
}
The backend updates last_heartbeat and calculates status.
7. Monitoring Agents
In the Filament dashboard:
Status view: online/offline agents with environment and role tags.
Recent log activity: count of events from Elasticsearch for last 24h.
Recent metrics: CPU load, disk usage, memory.
Log search: filter logs by agent, service, level, time range.
8. Scaling & Retention
Elasticsearch indices rotate daily (logs-%Y.%m.%d) with ILM (hot → delete after 30d).
InfluxDB buckets have retention (30–90 days) with downsampling.
For larger fleets, deploy ES and Influx in clusters and use load balancers.
9. Troubleshooting
Agent shows offline:
- Check systemctl status telegraf.
- Verify outbound network to central servers.
- Ensure tokens are valid.
Logs not parsed:
- Confirm log format (prefer JSON).
- Update grok patterns if using text logs.
High cardinality in Influx:
- Review tags (avoid request_id, user_id).
- Move high-cardinality fields to fields.
10. Best Practices
Use JSON logging for Nginx, Apache, Laravel.
Keep log levels consistent (debug, info, warn, error).
Limit per-agent permissions (principle of least privilege).
Rotate API keys/tokens regularly.
Monitor ingestion pipeline performance (Telegraf buffer, ES indexing rate, Influx writes).
Automate agent enrollment/deployment with Ansible or Terraform for scale.
11. Roadmap
Add auto-downsampling in InfluxDB.
Implement alerting (e.g., send Slack/email when agent offline > 5m).
Add support for custom app logs (Node.js, Go, Python).
Provide agent groups for easier bulk management.
