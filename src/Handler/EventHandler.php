<?php

declare(strict_types=1);

namespace EventIngestion\Handler;

use EventIngestion\Event\EventRepository;
use EventIngestion\Http\Request;
use EventIngestion\Http\Response;
use EventIngestion\Logger;

/**
 * Handler for GET /events/{id}
 * Returns the current state of an event for observability.
 */
final class EventHandler
{
    private EventRepository $repository;
    private Logger $logger;

    public function __construct()
    {
        $this->repository = new EventRepository();
        $this->logger = new Logger('event');
    }

    public function handle(Request $request): Response
    {
        $eventId = $request->getPathParam('id');

        if ($eventId === null || $eventId === '') {
            return Response::badRequest('Event ID is required');
        }

        try {
            $event = $this->repository->findById($eventId);

            if ($event === null) {
                $this->logger->info('Event not found', ['event_id' => $eventId]);
                return Response::notFound('Event not found');
            }

            $this->logger->debug('Event retrieved', [
                'event_id' => $eventId,
                'status' => $event->status->value,
            ]);

            return Response::ok($event->toDetailedArray());
        } catch (\Exception $e) {
            $this->logger->error('Failed to retrieve event', [
                'event_id' => $eventId,
                'error' => $e->getMessage(),
            ]);

            return Response::serverError('Failed to retrieve event');
        }
    }
}

