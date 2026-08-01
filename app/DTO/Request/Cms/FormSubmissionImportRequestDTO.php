<?php

declare(strict_types=1);

namespace App\DTO\Request\Cms;

use dcardenasl\Ci4ApiCore\Dto\BaseRequestDTO;

/**
 * Admin-only intake for backfilling historical submissions (e.g. the legacy
 * migration ETL). Unlike FormSubmissionCreateRequestDTO, it accepts an
 * explicit created_at/status so imported rows keep their real chronology
 * instead of being stamped with the import time.
 */
readonly class FormSubmissionImportRequestDTO extends BaseRequestDTO
{
    public string  $form_key;
    public ?int    $form_id;
    public ?int    $page_id;
    public ?int    $language_id;
    /** @var array<string, mixed> */
    public array   $form_data;
    public string  $status;
    public ?string $created_at;
    public ?string $ip_address;
    public ?string $user_agent;

    /**
     * @return array<string, string>
     */
    public function rules(): array
    {
        return [
            'form_key'   => 'required|string|max_length[50]',
            'form_data'  => 'required',
            'status'     => 'permit_empty|in_list[new,read,replied,spam,archived]',
            'created_at' => 'permit_empty|valid_date[Y-m-d H:i:s]',
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function map(array $data): void
    {
        $this->form_key    = (string) ($data['form_key'] ?? 'contact');
        $this->page_id     = isset($data['page_id']) ? (int) $data['page_id'] : null;
        $this->language_id = isset($data['language_id']) ? (int) $data['language_id'] : null;
        $this->form_data   = is_array($data['form_data'] ?? null) ? $data['form_data'] : [];
        $this->status      = (string) ($data['status'] ?? 'new');
        $this->created_at  = isset($data['created_at']) && $data['created_at'] !== '' ? (string) $data['created_at'] : null;
        $this->ip_address  = isset($data['ip_address']) && $data['ip_address'] !== '' ? (string) $data['ip_address'] : null;
        $this->user_agent  = isset($data['user_agent']) && $data['user_agent'] !== '' ? (string) $data['user_agent'] : null;

        // Resolve form_id from form_key, same as FormSubmissionCreateRequestDTO
        /** @var \App\Models\FormModel $formModel */
        $formModel     = model(\App\Models\FormModel::class);
        $form          = $formModel->where('form_key', $this->form_key)->where('is_active', 1)->first();
        $this->form_id = $form !== null ? (int) ($form->id ?? 0) : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'form_key'    => $this->form_key,
            'form_id'     => $this->form_id,
            'page_id'     => $this->page_id,
            'language_id' => $this->language_id,
            'form_data'   => $this->form_data,
            'status'      => $this->status,
            'created_at'  => $this->created_at,
            'ip_address'  => $this->ip_address,
            'user_agent'  => $this->user_agent,
        ];
    }
}
