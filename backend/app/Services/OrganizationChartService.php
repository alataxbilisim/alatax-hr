<?php

namespace App\Services;

use App\Models\Department;
use App\Models\Employee;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Organizasyon şeması — 3 görünüm modu.
 *
 * - people: manager_id zinciri
 * - department: departman parent_id hiyerarşisi
 * - hybrid: departman ağacı + her düğümde o departmanın personelleri
 */
class OrganizationChartService
{
    public const MODE_PEOPLE = 'people';

    public const MODE_DEPARTMENT = 'department';

    public const MODE_HYBRID = 'hybrid';

    /** @var list<string> */
    public const MODES = [
        self::MODE_PEOPLE,
        self::MODE_DEPARTMENT,
        self::MODE_HYBRID,
    ];

    /**
     * @return list<array<string, mixed>>
     */
    public function build(int $companyId, string $mode = self::MODE_PEOPLE): array
    {
        if (! in_array($mode, self::MODES, true)) {
            throw new InvalidArgumentException("Geçersiz org şeması modu: {$mode}");
        }

        return match ($mode) {
            self::MODE_DEPARTMENT => $this->buildDepartmentTree($companyId),
            self::MODE_HYBRID => $this->buildHybridTree($companyId),
            default => $this->buildPeopleTree($companyId),
        };
    }

    /**
     * Yönetici zinciri (mevcut davranış).
     *
     * @return list<array<string, mixed>>
     */
    private function buildPeopleTree(int $companyId): array
    {
        $employees = Employee::query()
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->with(['user:id,name,email', 'department:id,name'])
            ->get();

        $byManager = $employees->groupBy(fn (Employee $e) => $e->manager_id ?? 0);

        $build = function (Employee $employee) use (&$build, $byManager): array {
            $children = ($byManager->get($employee->id) ?? collect())
                ->map(fn (Employee $child) => $build($child))
                ->values()
                ->all();

            return $this->employeeNode($employee, $children);
        };

        return ($byManager->get(0) ?? collect())
            ->map(fn (Employee $root) => $build($root))
            ->values()
            ->all();
    }

    /**
     * Sadece departman hiyerarşisi.
     *
     * @return list<array<string, mixed>>
     */
    private function buildDepartmentTree(int $companyId): array
    {
        $departments = $this->loadDepartments($companyId);
        $byParent = $departments->groupBy(fn (Department $d) => $d->parent_id ?? 0);

        $build = function (Department $department) use (&$build, $byParent): array {
            $children = ($byParent->get($department->id) ?? collect())
                ->map(fn (Department $child) => $build($child))
                ->values()
                ->all();

            return $this->departmentNode($department, $children);
        };

        return ($byParent->get(0) ?? collect())
            ->map(fn (Department $root) => $build($root))
            ->values()
            ->all();
    }

    /**
     * Departman ağacı; her departmanın altında o departmana bağlı personeller.
     * Departmanı olmayan aktif personeller sentetik "unassigned" kökünde.
     *
     * @return list<array<string, mixed>>
     */
    private function buildHybridTree(int $companyId): array
    {
        $departments = $this->loadDepartments($companyId);
        $byParent = $departments->groupBy(fn (Department $d) => $d->parent_id ?? 0);

        $employees = Employee::query()
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->with(['user:id,name,email', 'department:id,name'])
            ->get();

        $byDepartment = $employees->groupBy(fn (Employee $e) => $e->department_id ?? 0);

        $build = function (Department $department) use (&$build, $byParent, $byDepartment): array {
            $childDepartments = ($byParent->get($department->id) ?? collect())
                ->map(fn (Department $child) => $build($child))
                ->values()
                ->all();

            $people = ($byDepartment->get($department->id) ?? collect())
                ->map(fn (Employee $employee) => $this->employeeNode($employee, []))
                ->values()
                ->all();

            return $this->departmentNode($department, array_merge($people, $childDepartments));
        };

        $roots = ($byParent->get(0) ?? collect())
            ->map(fn (Department $root) => $build($root))
            ->values()
            ->all();

        $unassigned = ($byDepartment->get(0) ?? collect())
            ->map(fn (Employee $employee) => $this->employeeNode($employee, []))
            ->values()
            ->all();

        if ($unassigned !== []) {
            $roots[] = [
                'type' => 'department',
                'department' => [
                    'id' => 0,
                    'name' => null,
                    'code' => 'unassigned',
                    'is_unassigned' => true,
                ],
                'children' => $unassigned,
                'expanded' => true,
            ];
        }

        return $roots;
    }

    /**
     * @return Collection<int, Department>
     */
    private function loadDepartments(int $companyId): Collection
    {
        return Department::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->with(['manager:id,name,email'])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /**
     * @param  list<array<string, mixed>>  $children
     * @return array<string, mixed>
     */
    private function employeeNode(Employee $employee, array $children): array
    {
        return [
            'type' => 'employee',
            'employee' => [
                'id' => $employee->id,
                'employee_code' => $employee->employee_code,
                'position' => $employee->position,
                'title' => $employee->title,
                'user' => $employee->user,
                'department' => $employee->department,
            ],
            'children' => $children,
            'expanded' => true,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $children
     * @return array<string, mixed>
     */
    private function departmentNode(Department $department, array $children): array
    {
        return [
            'type' => 'department',
            'department' => [
                'id' => $department->id,
                'name' => $department->name,
                'code' => $department->code,
                'manager' => $department->manager
                    ? [
                        'id' => $department->manager->id,
                        'name' => $department->manager->name,
                        'email' => $department->manager->email,
                    ]
                    : null,
                'is_unassigned' => false,
            ],
            'children' => $children,
            'expanded' => true,
        ];
    }
}
