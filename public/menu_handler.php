<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

try {
    $input = Request::input();
    Request::required($input, ['call_id']);

    $callId = (int)$input['call_id'];
    if ($callId <= 0) {
        Response::error('Invalid call_id.', 422);
    }

    $session = $callSessions->getById($callId);

    // Support option is a terminal action and does not need another digit.
    if ($session['state'] === 'support') {
        $result = $ivrService->handle($callId, '4', $input);
    } elseif (isset($input['digit'])) {
        $result = $ivrService->handle($callId, (string)$input['digit'], $input);
    } elseif ($session['state'] === 'voicemail') {
        $result = $ivrService->handle($callId, '', $input);
    } else {
        Response::error('Missing digit for the current IVR state.', 422);
    }

    Response::json([
        'call_id' => $callId,
        ...$result,
    ], ($result['success'] ?? false) ? 200 : 400);
} catch (RuntimeException $e) {
    Response::error($e->getMessage(), 404);
} catch (Throwable $e) {
    Response::error('Internal server error.', 500, [
        'detail' => $e->getMessage(),
    ]);
}
