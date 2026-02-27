# Reliable Webhook / Event Ingestion Service

A backend service designed to **reliably ingest and process webhook events** under real-world conditions such as retries, partial failures, and downstream outages.

This project demonstrates production-oriented backend engineering patterns including **at-least-once delivery**, **idempotency**, **retry with backoff**, and **explicit failure handling**.

## Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                    Docker Compose Environment                    │
│                                                                  │
│  ┌──────────────────┐           ┌──────────────────┐            │
│  │   API Container   │           │  Worker Container │            │
│  │                   │           │                   │            │
│  │  ┌─────────────┐ │           │  ┌─────────────┐  │            │
│  │  │    Nginx    │ │           │  │  PHP Worker │  │            │
│  │  └──────┬──────┘ │           │  │    Loop     │  │            │
│  │         │        │           │  └──────┬──────┘  │            │
│  │  ┌──────▼──────┐ │           │         │         │            │
│  │  │   PHP-FPM   │ │           │         │         │            │
│  │  └──────┬──────┘ │           │         │         │            │
│  └─────────┼────────┘           └─────────┼─────────┘            │
│            │                              │                      │
│            │      ┌───────────────┐       │                      │
│            └──────►    SQLite     ◄───────┘                      │
│                   │   (WAL mode)  │                              │
│                   └───────────────┘                              │
└─────────────────────────────────────────────────────────────────┘
```

## Features

- **Idempotent ingestion** - Duplicate webhook deliveries are safely deduplicated
- **Durable queue** - Accepted events are never lost (SQLite-backed)
- **Retry with backoff** - Transient failures are retried with exponential backoff and jitter
- **Dead-lettering** - Permanently failing events are quarantined after max attempts
- **Structured logging** - JSON logs with event correlation for debugging
- **Health endpoints** - Liveness and readiness probes for orchestration

## Prerequisites

- Docker and Docker Compose
- curl (for testing)

## Quick Start

### 1. Start the service

```bash
docker-compose up --build
```

This starts three containers:
- `init-db` - Initializes the SQLite database (runs once)
- `api` - HTTP API on port 8080
- `worker` - Background event processor

### 2. Verify the service is running

```bash
# Liveness check
curl http://localhost:8080/health

# Readiness check (verifies database connectivity)
curl http://localhost:8080/ready
```

### 3. Send a webhook event

```bash
curl -X POST http://localhost:8080/webhooks/stripe \
  -H "Content-Type: application/json" \
  -H "Idempotency-Key: payment-12345" \
  -d '{"type": "payment.completed", "amount": 1000, "currency": "usd"}'
```

Response:
```json
{
  "event_id": "evt_a1b2c3d4-e5f6-7890-abcd-ef1234567890",
  "status": "accepted"
}
```

### 4. Query event status

```bash
curl http://localhost:8080/events/evt_a1b2c3d4-e5f6-7890-abcd-ef1234567890
```

Response:
```json
{
  "id": "evt_a1b2c3d4-e5f6-7890-abcd-ef1234567890",
  "source": "stripe",
  "status": "delivered",
  "attempts": 1,
  "max_attempts": 5,
  "last_error": null,
  "next_retry_at": null,
  "created_at": "2026-01-06T10:15:30Z",
  "updated_at": "2026-01-06T10:15:31Z",
  "payload": {
    "type": "payment.completed",
    "amount": 1000,
    "currency": "usd"
  },
  "status_description": "Successfully processed"
}
```

## API Reference

### POST /webhooks/{source}

Accepts an incoming webhook event from a specified source.

**Path Parameters:**
| Parameter | Description |
|-----------|-------------|
| `source` | Webhook source identifier (e.g., `stripe`, `github`, `shopify`) |

**Required Headers:**
| Header | Description |
|--------|-------------|
| `Idempotency-Key` | Unique key for deduplication (provided by webhook sender) |
| `Content-Type` | Must be `application/json` |

**Request Body:** Any valid JSON object representing the event payload.

**Responses:**

| Status | Description |
|--------|-------------|
| `202 Accepted` | Event accepted for processing |
| `400 Bad Request` | Missing idempotency key or invalid JSON |
| `500 Internal Server Error` | Server error |

**Example:**
```bash
curl -X POST http://localhost:8080/webhooks/github \
  -H "Content-Type: application/json" \
  -H "Idempotency-Key: delivery-789" \
  -d '{"action": "push", "ref": "refs/heads/main"}'
```

---

### GET /events/{id}

Returns the current state of an event. Useful for debugging and observability.

**Path Parameters:**
| Parameter | Description |
|-----------|-------------|
| `id` | Event ID (returned from POST /webhooks) |

**Responses:**

| Status | Description |
|--------|-------------|
| `200 OK` | Event found |
| `404 Not Found` | Event does not exist |

---

### GET /health

Liveness probe - confirms the process is running.

**Response:** `200 OK`
```json
{
  "status": "healthy",
  "timestamp": "2026-01-06T10:15:30Z"
}
```

---

### GET /ready

Readiness probe - confirms database connectivity.

**Responses:**

| Status | Description |
|--------|-------------|
| `200 OK` | Service is ready |
| `503 Service Unavailable` | Database connection failed |

```json
{
  "status": "ready",
  "timestamp": "2026-01-06T10:15:30Z",
  "checks": {
    "database": "connected"
  }
}
```

## Event Lifecycle

Events transition through explicit states:

```
         ┌──────────────────────────────────────────┐
         │                                          │
         ▼                                          │
    ┌─────────┐     ┌────────────┐     ┌──────────┐│
───►│ QUEUED  │────►│ PROCESSING │────►│DELIVERED ││
    └─────────┘     └─────┬──────┘     └──────────┘│
                          │                        │
                          │ failure                │
                          ▼                        │
                    ┌──────────┐                   │
                    │ RETRYING │───────────────────┘
                    └────┬─────┘    retry time reached
                         │
                         │ max attempts exceeded
                         ▼
                  ┌──────────────┐
                  │ DEAD_LETTERED│
                  └──────────────┘
```

| Status | Description |
|--------|-------------|
| `queued` | Awaiting first processing attempt |
| `processing` | Currently being processed by worker |
| `retrying` | Failed, scheduled for retry |
| `delivered` | Successfully processed |
| `dead_lettered` | Permanently failed after max attempts |

## Retry Strategy

Failed events are retried with exponential backoff and jitter:

| Attempt | Base Delay | With Jitter |
|---------|------------|-------------|
| 1 | 10s | 8-12s |
| 2 | 30s | 24-36s |
| 3 | 90s | 72-108s |
| 4 | 270s | 216-324s |
| 5 | Dead-lettered | — |

The jitter prevents "thundering herd" problems when multiple events fail simultaneously.

## Testing Scenarios

The event processor supports simulation flags in the payload for testing:

### Simulate a transient failure (succeeds on 3rd attempt)

```bash
curl -X POST http://localhost:8080/webhooks/test \
  -H "Content-Type: application/json" \
  -H "Idempotency-Key: transient-test-1" \
  -d '{"simulate_transient": true}'
```

### Simulate a permanent failure (dead-letters after 5 attempts)

```bash
curl -X POST http://localhost:8080/webhooks/test \
  -H "Content-Type: application/json" \
  -H "Idempotency-Key: failure-test-1" \
  -d '{"simulate_failure": true}'
```

### Simulate processing delay

```bash
curl -X POST http://localhost:8080/webhooks/test \
  -H "Content-Type: application/json" \
  -H "Idempotency-Key: delay-test-1" \
  -d '{"simulate_delay_ms": 2000}'
```

### Test idempotency (send same event twice)

```bash
# First request - accepted
curl -X POST http://localhost:8080/webhooks/test \
  -H "Content-Type: application/json" \
  -H "Idempotency-Key: idempotency-test" \
  -d '{"data": "first"}'

# Second request with same key - returns existing event_id
curl -X POST http://localhost:8080/webhooks/test \
  -H "Content-Type: application/json" \
  -H "Idempotency-Key: idempotency-test" \
  -d '{"data": "second"}'
```

## Viewing Logs

```bash
# All services
docker-compose logs -f

# API only
docker-compose logs -f api

# Worker only
docker-compose logs -f worker
```

Logs are structured JSON for easy parsing:

```json
{
  "timestamp": "2026-01-06T10:15:30Z",
  "level": "info",
  "component": "worker",
  "message": "Event delivered",
  "context": {
    "event_id": "evt_abc123",
    "source": "stripe",
    "total_attempts": 1
  }
}
```

## Project Structure

```
EventIngestionService/
├── docker/
│   ├── api/
│   │   ├── Dockerfile        # PHP-FPM + Nginx
│   │   └── nginx.conf        # Nginx configuration
│   ├── worker/
│   │   └── Dockerfile        # PHP CLI for worker
│   └── php/
│       └── php.ini           # PHP configuration
├── src/
│   ├── bootstrap.php         # Autoloader and initialization
│   ├── Database.php          # SQLite connection (singleton)
│   ├── Logger.php            # Structured JSON logger
│   ├── Http/
│   │   ├── Router.php        # Simple path-based routing
│   │   ├── Request.php       # HTTP request wrapper
│   │   └── Response.php      # HTTP response builder
│   ├── Event/
│   │   ├── Event.php         # Event entity
│   │   ├── EventStatus.php   # Status enum
│   │   ├── EventRepository.php    # Database operations
│   │   └── IdempotencyService.php # Duplicate detection
│   ├── Handler/
│   │   ├── WebhookHandler.php     # POST /webhooks/{source}
│   │   ├── EventHandler.php       # GET /events/{id}
│   │   └── HealthHandler.php      # Health endpoints
│   └── Worker/
│       ├── Worker.php        # Main worker loop
│       ├── EventProcessor.php # Event processing logic
│       └── RetryStrategy.php  # Backoff calculation
├── public/
│   └── index.php             # API entry point
├── bin/
│   ├── init-db.php           # Database initialization
│   └── worker.php            # Worker entry point
├── data/                     # SQLite database (volume)
├── docker-compose.yml
├── composer.json
└── README.md
```

## Design Decisions

| Decision | Rationale |
|----------|-----------|
| **SQLite** | Simple, zero-configuration, suitable for demo/small scale. WAL mode enables concurrent reads. |
| **No external queue** | Reduces infrastructure complexity. Database-backed queue is sufficient for moderate throughput. |
| **Vanilla PHP** | No framework dependencies. Focus on reliability patterns, not framework features. |
| **Single worker** | Simpler than distributed workers. Can scale by running multiple containers if needed. |
| **Exponential backoff with jitter** | Industry standard for retry. Jitter prevents thundering herd on mass failures. |
| **Explicit state machine** | Makes event lifecycle visible and debuggable. No implicit state transitions. |

## Stopping the Service

```bash
# Stop all containers
docker-compose down

# Stop and remove volumes (deletes database)
docker-compose down -v
```

## Troubleshooting

### "Database connection failed" on /ready

The SQLite database file may not exist. Ensure the `init-db` container ran successfully:

```bash
docker-compose logs init-db
```

### Events stuck in "processing" state

If the worker crashes while processing an event, it will remain in `processing` state. In a production system, you would implement a timeout/heartbeat mechanism. For this demo, restart the containers:

```bash
docker-compose restart worker
```

### Permission denied on data directory

Ensure the `data/` directory exists and is writable:

```bash
mkdir -p data
chmod 755 data
```

## Future Improvements

This demo intentionally omits:

- Authentication/authorization
- External message queues (Kafka, SQS)
- Distributed tracing
- Metrics/dashboards
- Horizontal worker scaling
- Event replay/schema versioning

These would be natural additions for a production system at scale.

## License

MIT
