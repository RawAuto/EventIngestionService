# Reliable Webhook / Event Ingestion Service

> **🚧 Work in Progress** — This project is under active development and not yet complete.

A small backend service designed to **reliably ingest and process webhook events** under real-world conditions such as retries, partial failures, and downstream outages.

This project focuses on **systems thinking, reliability, and operational correctness**, rather than feature breadth or infrastructure complexity.

## Goals

- Accept webhook events safely using **at-least-once delivery semantics**
- Ensure **no duplicate processing** through idempotency
- Decouple ingestion from processing to improve reliability
- Demonstrate **resilience under failure** (retries, timeouts, restarts)
- Provide clear **observability and debuggability** for operators

## Tech Stack

- **PHP 8.3** (vanilla, no framework)
- **SQLite** (database-backed durable queue)
- **Docker** (containerised deployment)

## Planned Architecture

```
┌─────────────────┐      ┌─────────────────┐      ┌─────────────────┐
│ Webhook Producer│─────►│    HTTP API     │─────►│     SQLite      │
└─────────────────┘      │  (accepts POST) │      │  (event queue)  │
                         └─────────────────┘      └────────┬────────┘
                                                           │
                                                           ▼
                                                  ┌─────────────────┐
                                                  │  Background     │
                                                  │  Worker         │
                                                  │  (processes     │
                                                  │   with retry)   │
                                                  └─────────────────┘
```

## Planned Features

- [ ] Webhook ingestion endpoint with idempotency key support
- [ ] Database-backed event queue with explicit state machine
- [ ] Background worker with exponential backoff retry
- [ ] Dead-letter handling for permanently failing events
- [ ] Health and readiness endpoints
- [ ] Structured JSON logging with event correlation

## Reliability Patterns

This project will demonstrate:

| Pattern | Description |
|---------|-------------|
| **Idempotency** | Duplicate webhook deliveries are safely deduplicated |
| **Durability** | Accepted events are never lost |
| **Retry with backoff** | Transient failures are retried with exponential backoff |
| **Failure isolation** | Downstream outages do not block ingestion |
| **Dead-lettering** | Permanently failing events are quarantined |

## Non-Goals

This project intentionally does **not** include:

- Authentication or authorisation
- External message queues (Kafka, SQS)
- Distributed tracing or metrics backends
- UI or frontend components

These are deferred to keep the focus on **core reliability patterns**.

## Status

This is an intentionally scoped system designed to demonstrate production-oriented backend engineering judgement. Check back for updates as development progresses.

## Quick Start

### 1. Start the service

```bash
docker-compose up --build
```

This starts one container (so far):
- `init-db` - Initialises the SQLite database (runs once)
---

*See [PLAN_DOCUMENT.md](PLAN_DOCUMENT.md) for detailed design notes.*
