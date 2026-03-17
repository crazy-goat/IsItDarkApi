<?php

namespace app\middleware;

use app\service\ResponseFormatterService;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

class ApiErrorMiddleware implements MiddlewareInterface
{
    private ResponseFormatterService $formatter;

    public function __construct(ResponseFormatterService $formatter)
    {
        $this->formatter = $formatter;
    }

    public function process(Request $request, callable $handler): Response
    {
        try {
            $response = $handler($request);
            
            // Jeśli to błąd 4xx/5xx i ścieżka zaczyna się od /api/, formatujemy odpowiedź
            $statusCode = $response->getStatusCode();
            if ($statusCode >= 400 && strpos($request->path(), '/api/') === 0) {
                return $this->formatErrorResponse($request, $response, $statusCode);
            }
            
            return $response;
        } catch (\Throwable $e) {
            if (strpos($request->path(), '/api/') === 0) {
                return $this->createErrorResponse($request, 500, 'Internal Server Error');
            }
            throw $e;
        }
    }

    private function formatErrorResponse(Request $request, Response $response, int $statusCode): Response
    {
        $body = json_decode($response->rawBody(), true);
        $message = $body['message'] ?? $this->getDefaultMessage($statusCode);
        
        return $this->createErrorResponse($request, $statusCode, $message);
    }

    private function createErrorResponse(Request $request, int $statusCode, string $message): Response
    {
        $acceptHeader = $request->header('Accept', 'application/json');
        $format = $this->formatter->detectFormat($acceptHeader);
        
        $data = [
            'error' => true,
            'status' => $statusCode,
            'message' => $message,
        ];
        
        $body = $this->formatter->format($data, $format);
        $contentType = $this->formatter->getContentType($format);
        
        return new Response($statusCode, ['Content-Type' => $contentType], $body);
    }

    private function getDefaultMessage(int $statusCode): string
    {
        return match ($statusCode) {
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            422 => 'Unprocessable Entity',
            500 => 'Internal Server Error',
            default => 'Error',
        };
    }
}
