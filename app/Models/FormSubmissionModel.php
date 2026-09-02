<?php

declare(strict_types=1);

namespace App\Models;

use App\Entities\FormSubmissionEntity;
use CodeIgniter\Model;

class FormSubmissionModel extends Model
{
    protected $table      = 'cms_form_submissions';
    protected $primaryKey = 'id';
    protected $returnType = FormSubmissionEntity::class;

    protected $useTimestamps  = true;
    protected $useSoftDeletes = false;

    protected $allowedFields = [
        'form_id',
        'form_key',
        'page_id',
        'language_id',
        'data_json',
        'status',
        'ip_address',
        'user_agent',
        'is_anonymized',
        'anonymized_at',
    ];

    /** @var array<string, string> */
    protected $validationRules = [
        'form_key'  => 'required|string|max_length[50]',
        'data_json' => 'required|string',
        'status'    => 'permit_empty|in_list[new,read,replied,spam,archived]',
    ];

    /**
     * Count submissions grouped by status, for badge counters in admin.
     * Extracted from FormSubmissionService::countByStatus() (LAYER-03),
     * which used to run this single-table GROUP BY via `Database::connect()`
     * directly instead of through this model.
     *
     * @return array<string, int>
     */
    public function countByStatus(): array
    {
        $query = $this->builder()
            ->select('status, COUNT(*) as total')
            ->groupBy('status')
            ->get();
        $rows = $query ? $query->getResultArray() : [];

        $counts = ['new' => 0, 'read' => 0, 'replied' => 0, 'spam' => 0, 'archived' => 0];
        foreach ($rows as $row) {
            $counts[(string) $row['status']] = (int) $row['total'];
        }

        return $counts;
    }

    /**
     * Count submissions belonging to a form. Extracted from
     * FormService::destroy() (LAYER-03), which used a raw `->countAllResults()`
     * against an injected BaseConnection to decide whether to soft-disable a
     * form (has submission history) instead of hard-deleting it.
     */
    public function countByFormId(int $formId): int
    {
        return (int) $this->where('form_id', $formId)->countAllResults();
    }
}
