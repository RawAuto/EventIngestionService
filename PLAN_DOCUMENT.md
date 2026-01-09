# Reliable Webhook / Event Ingestion Service

A small backend service designed to **reliably ingest and process webhook events** under real-world conditions such as retries, partial failures, and downstream outages.

The project focuses on **systems thinking, reliability, and operational correctness**, rather than feature breadth or infrastructure complexity.

This is an intentionally scoped system designed to demonstrate production-oriented backend engineering judgement.

---

## Goals

- Accept webhook events safely using **at-least-once delivery semantics**
- Ensure **no duplicate processing** through idempotency
- Decouple ingestion from processing to improve reliability
- Demonstrate **resilience under failure** (retries, timeouts, restarts)
- Provide clear **observability and debuggability** for operators
- Balance correctness, simplicity, and pragmatism

---

## Non-Goals

This project intentionally does **not** include:

- Authentication or authorisation
- External message queues (e.g. Kafka, SQS)
- Distributed tracing or metrics backends
- UI or frontend components
- Guaranteed exactly-once delivery

These are deferred to keep the focus on **core reliability patterns**.

---

## System Overview

The service accepts incoming webhook events via HTTP, persists them durably, and processes them asynchronously using a worker.

Key characteristics:
- Ingestion is fast and isolated from downstream dependencies
- Processing is retried safely with backoff
- Failures are explicit and visible
- Events are never lost once accepted

---

## API Overview

### `POST /webhooks/{source}`

Accepts an incoming webhook event.

**Characteristics:**
- Returns quickly with `202 Accepted`
- Requires an idempotency key to deduplicate retries
- Persists events durably before returning

**Expected headers:**
- `Idempotency-Key`

**Response fields:**
- `event_id`
- `status`

---

### `GET /events/{id}`

Returns the current state of an event.

This endpoint exists primarily for **observability and debugging**, allowing operators to understand:
- Whether an event has been delivered
- How many attempts have been made
- Whether it is scheduled for retry or dead-lettered

---

### Health Endpoints

- `GET /health` – process is running
- `GET /ready` – database connectivity confirmed

---

## Design Assumptions

- Webhook producers retry aggressively and may deliver duplicates
- Downstream systems are unreliable and may timeout or fail
- The service may be restarted during processing
- Correctness and predictability are more important than raw throughput

---

## Reliability Guarantees

The system provides the following guarantees:

- **Idempotency**: duplicate deliveries are deduplicated safely
- **Durability**: accepted events are never lost
- **Retry safety**: transient failures are retried with backoff
- **Failure isolation**: downstream outages do not block ingestion
- **Dead-lettering**: permanently failing events are quarantined

---

## Processing Model

- Events are stored in a database-backed queue
- A worker process claims events safely using database locking
- Events transition through explicit states (queued, processing, retrying, delivered, dead-lettered)
- Retry schedules are persisted to avoid retry storms

---

## Observability

The service is designed to be operable in production environments:

- Structured logs with correlation via `event_id`
- Explicit event state visible via API
- Deterministic error handling and status transitions
- Health and readiness endpoints for orchestration

---

## Trade-Offs & Decisions

This project favours:

- Simplicity over abstraction
- Explicit state over implicit behaviour
- Database-backed durability over external infrastructure
- Predictability over throughput

Several design choices (e.g. retry strategy, queue implementation) are intentionally modest to reflect how systems often start in real-world environments.

---

## Future Evolution

At higher scale, this system could evolve to include:

- External queues (e.g. SQS, Kafka)
- Circuit breakers for downstream dependencies
- Metrics and dashboards
- Horizontal worker scaling
- Schema versioning and event replay

These are intentionally deferred.

---

## Summary

This project is not intended to be feature-complete.

It is designed to demonstrate **how reliable backend systems are shaped**, how failure is handled explicitly, and how pragmatic engineering decisions are made under real-world constraints.

