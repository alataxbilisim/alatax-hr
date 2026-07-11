<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;

trait ApiResponse
{
    /**
     * Başarılı response döndür
     */
    protected function success($data = null, string $message = 'İşlem başarılı', int $code = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'errors' => null,
            'timestamp' => now()->toDateTimeString(),
        ], $code);
    }

    /**
     * Hata response döndür
     */
    protected function error(string $message = 'Bir hata oluştu', int $code = 400, $errors = null): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data' => null,
            'errors' => $errors,
            'timestamp' => now()->toDateTimeString(),
        ], $code);
    }

    /**
     * Sayfalanmış response döndür.
     *
     * @param  \Illuminate\Contracts\Pagination\LengthAwarePaginator|\Illuminate\Support\Collection|array  $paginator
     * @param  \Illuminate\Contracts\Pagination\LengthAwarePaginator|null  $metaSource  Dönüştürülmüş item listesi için meta kaynağı
     */
    protected function paginated($paginator, string $message = 'Veriler listelendi', $metaSource = null): JsonResponse
    {
        if ($metaSource !== null) {
            $items = $paginator instanceof \Illuminate\Support\Collection
                ? $paginator->values()->all()
                : (array) $paginator;

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => $items,
                'meta' => [
                    'current_page' => $metaSource->currentPage(),
                    'last_page' => $metaSource->lastPage(),
                    'per_page' => $metaSource->perPage(),
                    'total' => $metaSource->total(),
                ],
                'errors' => null,
                'timestamp' => now()->toDateTimeString(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'errors' => null,
            'timestamp' => now()->toDateTimeString(),
        ]);
    }

    /**
     * 201 Created response
     */
    protected function created($data = null, string $message = 'Kayıt oluşturuldu'): JsonResponse
    {
        return $this->success($data, $message, 201);
    }

    /**
     * 204 No Content response
     */
    protected function noContent(): JsonResponse
    {
        return response()->json(null, 204);
    }

    /**
     * 401 Unauthorized response
     */
    protected function unauthorized(string $message = 'Yetkisiz erişim'): JsonResponse
    {
        return $this->error($message, 401);
    }

    /**
     * 403 Forbidden response
     */
    protected function forbidden(string $message = 'Bu işlem için yetkiniz yok'): JsonResponse
    {
        return $this->error($message, 403);
    }

    /**
     * 404 Not Found response
     */
    protected function notFound(string $message = 'Kayıt bulunamadı'): JsonResponse
    {
        return $this->error($message, 404);
    }

    /**
     * 422 Validation Error response
     */
    protected function validationError($errors, string $message = 'Doğrulama hatası'): JsonResponse
    {
        return $this->error($message, 422, $errors);
    }

    /**
     * 500 Server Error response
     */
    protected function serverError(string $message = 'Sunucu hatası'): JsonResponse
    {
        return $this->error($message, 500);
    }
}
