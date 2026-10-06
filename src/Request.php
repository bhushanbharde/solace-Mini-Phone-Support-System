<?php
declare(strict_types=1);

final class Request
{
    public static function input(): array
    {
        $input = $_GET + $_POST;

        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (str_contains(strtolower($contentType), 'application/json')) {
            $raw = file_get_contents('php://input');
            if ($raw !== false && trim($raw) !== '') {
                $json = json_decode($raw, true);
                if (is_array($json)) {
                    $input = array_merge($input, $json);
                }
            }
        }

        return $input;
    }

    public static function required(array $input, array $fields): void
    {
        foreach ($fields as $field) {
            if (!isset($input[$field]) || trim((string)$input[$field]) === '') {
                Response::error("Missing required field: {$field}", 422);
            }
        }
    }
}
