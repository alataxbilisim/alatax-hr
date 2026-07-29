<?php

namespace App\Services\Reports;

use App\Models\User;
use App\Services\Reports\Datasets\AssetsDataset;
use App\Services\Reports\Datasets\AttendanceRecordsDataset;
use App\Services\Reports\Datasets\EmployeeDocumentsDataset;
use App\Services\Reports\Datasets\EmployeesDataset;
use App\Services\Reports\Datasets\ExpenseClaimsDataset;
use App\Services\Reports\Datasets\JobApplicationsDataset;
use App\Services\Reports\Datasets\LeaveBalancesDataset;
use App\Services\Reports\Datasets\LeaveRequestsDataset;
use App\Services\Reports\Datasets\PayslipsDataset;
use App\Services\Reports\Datasets\SurveyResponsesDataset;
use App\Services\Reports\Datasets\TrainingParticipantsDataset;
use InvalidArgumentException;

/**
 * Dataset semantic layer kaydı.
 */
class DatasetRegistry
{
    /** @var array<string, AbstractDataset>|null */
    private ?array $datasets = null;

    /**
     * @return array<string, AbstractDataset>
     */
    public function all(): array
    {
        if ($this->datasets === null) {
            $list = [
                new EmployeesDataset,
                new LeaveRequestsDataset,
                new LeaveBalancesDataset,
                new ExpenseClaimsDataset,
                new JobApplicationsDataset,
                new SurveyResponsesDataset,
                new AttendanceRecordsDataset,
                new AssetsDataset,
                new TrainingParticipantsDataset,
                new EmployeeDocumentsDataset,
                new PayslipsDataset,
            ];
            $this->datasets = [];
            foreach ($list as $ds) {
                $this->datasets[$ds->key()] = $ds;
            }
        }

        return $this->datasets;
    }

    public function get(string $key): AbstractDataset
    {
        $all = $this->all();
        if (! isset($all[$key])) {
            throw new InvalidArgumentException('Bilinmeyen dataset: '.$key);
        }

        return $all[$key];
    }

    public function has(string $key): bool
    {
        return isset($this->all()[$key]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function catalog(User $user, int $companyId): array
    {
        $out = [];
        foreach ($this->all() as $ds) {
            $out[] = $ds->catalog($user, $companyId);
        }

        return $out;
    }
}
