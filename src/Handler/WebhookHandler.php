<?php

declare(strict_types=1);

namespace EventIngestion\Handler;

use EventIngestion\Event\Event;
use EventIngestion\Event\EventRepository;
use EventIngestion\Event\IdempotencyService;
use EventIngestion\Http\Request;
use EventIngestion\Http\Response;
use EventIngestion\Logger;
use Exception;

/**
 * Handler for POST /webhooks/{source}
 * Accepts incoming webhook events with idempotency support.
 */
final class WebhookHandler
{
    private EventRepository $repository;
    private IdempotencyService $idempotencyService;
    private Logger $logger;

    public function __construct()
    {
        $this->repository = new EventRepository();
        $this->idempotencyService = new IdempotencyService();
        $this->logger = new Logger('webhook');
    }

    public function handle(Request $request): Response
    {
        $source = $request->getPathParam('source');

        if ($source === null || $source === '') {
            return Response::badRequest('Source is required');
        }

        // Validate idempotency key
        $idempotencyKey = $request->getHeader('idempotency-key');

        if ($idempotencyKey === null || $idempotencyKey === '') {
            $this->logger->warning('Missing idempotency key', ['source' => $source]);
            return Response::badRequest('Idempotency-Key header is required');
        }

        // Parse request body
        $payload = $request->getJsonBody();

        if ($payload === null) {
            $this->logger->warning('Invalid JSON payload', [
                'source' => $source,
                'idempotency_key' => $idempotencyKey,
            ]);
            return Response::badRequest('Invalid JSON payload');
        }

        try {
            // Check for existing event with same idempotency key
            $existingEventId = $this->idempotencyService->findExistingEventId($source, $idempotencyKey);

            if ($existingEventId !== null) {
                $this->logger->info('Duplicate event detected', [
                    'source' => $source,
                    'idempotency_key' => $idempotencyKey,
                    'event_id' => $existingEventId,
                ]);

                return Response::accepted([
                    'event_id' => $existingEventId,
                    'status' => 'duplicate',
                ]);
            }

            // Create and persist new event
            $event = Event::create($source, $idempotencyKey, $payload);

            $this->repository->beginTransaction();

            try {
                $this->repository->save($event);
                $this->idempotencyService->store($source, $idempotencyKey, $event->id);
                $this->repository->commit();
            } catch (Exception $e) {
                $this->repository->rollback();
                throw $e;
            }

            $this->logger->info('Event accepted', [
                'event_id' => $event->id,
                'source' => $source,
                'idempotency_key' => $idempotencyKey,
            ]);

            return Response::accepted([
                'event_id' => $event->id,
                'status' => 'accepted',
            ]);
        } catch (Exception $e) {
            $this->logger->error('Failed to process webhook', [
                'source' => $source,
                'idempotency_key' => $idempotencyKey,
                'error' => $e->getMessage(),
            ]);

            return Response::serverError('Failed to process webhook');
        }
    }
}

