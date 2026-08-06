<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1\Cms;

use App\DTO\Request\Cms\SettingConnectionCreateRequestDTO;
use App\Interfaces\Cms\SettingConnectionServiceInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;
use dcardenasl\Ci4ApiCore\Dto\SecurityContext;
use dcardenasl\Ci4ApiCore\Http\ApiController;
use dcardenasl\Ci4ApiCore\Support\ApiResult;

class SettingConnectionController extends ApiController
{
    protected array $statusCodes = [
        'create' => 201,
    ];

    protected SettingConnectionServiceInterface $settingConnectionService;

    protected function resolveDefaultService(): SettingConnectionServiceInterface
    {
        $this->settingConnectionService = Services::settingConnectionService();

        return $this->settingConnectionService;
    }

    public function index(int $settingId): ResponseInterface
    {
        return $this->handleRequest(
            function (array $dto, SecurityContext $context) use ($settingId): array {
                $result = $this->settingConnectionService->listForSetting($settingId);

                return ['status' => 'success', 'data' => $result];
            }
        );
    }

    public function create(int $settingId): ResponseInterface
    {
        return $this->handleRequest(
            function (SettingConnectionCreateRequestDTO $dto, SecurityContext $context) use ($settingId): ApiResult {
                $data = $this->settingConnectionService->create(
                    $settingId,
                    $dto->entityType,
                    $dto->entityKey,
                    $dto->usageLabel
                );

                return new ApiResult(['status' => 'success', 'data' => $data], 201);
            },
            SettingConnectionCreateRequestDTO::class
        );
    }

    public function delete(int $settingId, int $connectionId): ResponseInterface
    {
        return $this->handleRequest(
            function (array $dto, SecurityContext $context) use ($settingId, $connectionId): array {
                $this->settingConnectionService->delete($settingId, $connectionId);

                return ['status' => 'success', 'data' => null];
            }
        );
    }
}
