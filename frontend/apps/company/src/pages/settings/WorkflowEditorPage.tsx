import React, { useCallback, useEffect, useMemo, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { useTranslation } from '@shared/i18n';
import { Select } from '@shared/components';
import {
  rolesApi,
  usersApi,
  workflowsApi,
  type WorkflowStepPayload,
} from '@shared/services/api';
import { getErrorMessage } from '@shared/services/apiHelpers';
import toast from 'react-hot-toast';
import {
  BsArrowDown,
  BsArrowLeft,
  BsArrowUp,
  BsCopy,
  BsPlus,
  BsSave,
  BsTrash,
} from 'react-icons/bs';

type EditorStep = WorkflowStepPayload & { _key: string };

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && !Array.isArray(value);
}

function toStringRecord(value: unknown): Record<string, string> {
  if (!isRecord(value)) return {};
  const out: Record<string, string> = {};
  for (const [k, v] of Object.entries(value)) {
    if (typeof v === 'string') out[k] = v;
  }
  return out;
}

function newKey(): string {
  return `s-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`;
}

function emptyStep(): EditorStep {
  return {
    _key: newKey(),
    name: '',
    approver_type: 'dynamic_manager',
    specific_user_id: null,
    specific_role: null,
    condition: null,
    parallel_group: null,
    completion_policy: 'all',
    escalation_days: null,
    is_required: true,
  };
}

function buildPreview(steps: EditorStep[]): string {
  if (steps.length === 0) return '';

  const groups = new Map<string, string[]>();
  const order: string[] = [];

  steps.forEach((step, index) => {
    const label = step.name.trim() || `#${index + 1}`;
    const g = step.parallel_group;
    if (g == null) {
      const key = `seq-${index}`;
      order.push(key);
      groups.set(key, [label]);
      return;
    }
    const key = `p-${g}`;
    if (!groups.has(key)) {
      order.push(key);
      groups.set(key, []);
    }
    groups.get(key)?.push(label);
  });

  return order
    .map((key) => {
      const labels = groups.get(key) ?? [];
      if (key.startsWith('p-') && labels.length > 1) {
        return labels.join(' ∥ ');
      }
      return labels[0] ?? '';
    })
    .filter(Boolean)
    .join(' → ');
}

const DYNAMIC_TYPES: readonly string[] = [
  'dynamic_manager',
  'dynamic_skip_manager',
  'department_head',
];
const ROLE_TYPES: readonly string[] = ['role', 'specific_role', 'hr', 'cfo', 'ceo'];
const USER_TYPES: readonly string[] = ['user', 'specific_user'];

function isDynamic(type: string): boolean {
  return DYNAMIC_TYPES.includes(type);
}
function isRole(type: string): boolean {
  return ROLE_TYPES.includes(type);
}
function isUser(type: string): boolean {
  return USER_TYPES.includes(type);
}

const WorkflowEditorPage: React.FC = () => {
  const { t } = useTranslation('common');
  const navigate = useNavigate();
  const { id: idParam } = useParams<{ id: string }>();
  const isNew = idParam === 'new' || !idParam;
  const workflowId = isNew ? null : Number(idParam);

  const [loading, setLoading] = useState(!isNew);
  const [saving, setSaving] = useState(false);
  const [name, setName] = useState('');
  const [entityType, setEntityType] = useState('leave_request');
  const [description, setDescription] = useState('');
  const [isActive, setIsActive] = useState(true);
  const [isDefault, setIsDefault] = useState(false);
  const [escalationDays, setEscalationDays] = useState('');
  const [steps, setSteps] = useState<EditorStep[]>([emptyStep()]);
  const [entityTypes, setEntityTypes] = useState<Record<string, string>>({});
  const [approverTypes, setApproverTypes] = useState<Record<string, string>>({});
  const [conditionFields, setConditionFields] = useState<string[]>([]);
  const [conditionOps, setConditionOps] = useState<string[]>([]);
  const [roles, setRoles] = useState<Array<{ value: string; label: string }>>([]);
  const [users, setUsers] = useState<Array<{ value: string; label: string }>>([]);

  const loadMeta = useCallback(async () => {
    const [et, at, cm, rolesRes, usersRes] = await Promise.all([
      workflowsApi.entityTypes(),
      workflowsApi.approverTypes(),
      workflowsApi.conditionMeta(),
      rolesApi.list(),
      usersApi.list({ per_page: 100 }),
    ]);

    const etData = et.data.data;
    setEntityTypes(toStringRecord(etData));
    setApproverTypes(toStringRecord(at.data.data));
    const metaRaw = cm.data.data;
    if (isRecord(metaRaw)) {
      setConditionFields(
        Array.isArray(metaRaw.fields)
          ? metaRaw.fields.filter((f): f is string => typeof f === 'string')
          : []
      );
      setConditionOps(
        Array.isArray(metaRaw.ops)
          ? metaRaw.ops.filter((o): o is string => typeof o === 'string')
          : []
      );
    }

    const roleList = rolesRes.data.data;
    if (Array.isArray(roleList)) {
      setRoles(
        roleList
          .map((r) => {
            if (!isRecord(r) || typeof r.name !== 'string') return null;
            return { value: r.name, label: r.name };
          })
          .filter((r): r is { value: string; label: string } => r !== null)
      );
    }

    const userPayload = usersRes.data.data;
    let userRows: unknown[] = [];
    if (Array.isArray(userPayload)) {
      userRows = userPayload;
    } else if (isRecord(userPayload) && Array.isArray(userPayload.data)) {
      userRows = userPayload.data;
    }
    setUsers(
      userRows
        .map((u) => {
          if (!isRecord(u) || typeof u.id !== 'number') return null;
          const label =
            typeof u.name === 'string'
              ? `${u.name}${typeof u.email === 'string' ? ` (${u.email})` : ''}`
              : String(u.id);
          return { value: String(u.id), label };
        })
        .filter((u): u is { value: string; label: string } => u !== null)
    );
  }, []);

  const loadWorkflow = useCallback(async () => {
    if (workflowId == null || Number.isNaN(workflowId)) return;
    try {
      setLoading(true);
      const res = await workflowsApi.get(workflowId);
      const data = res.data.data;
      if (!isRecord(data) || typeof data.name !== 'string' || typeof data.entity_type !== 'string') {
        throw new Error('invalid workflow payload');
      }
      setName(data.name);
      setEntityType(data.entity_type);
      setDescription(typeof data.description === 'string' ? data.description : '');
      setIsActive(data.is_active !== false);
      setIsDefault(Boolean(data.is_default));
      setEscalationDays(
        typeof data.escalation_days === 'number' ? String(data.escalation_days) : ''
      );
      const rawSteps = Array.isArray(data.steps) ? data.steps : [];
      setSteps(
        rawSteps
          .filter(isRecord)
          .map((s) => {
            const step: EditorStep = {
              _key: newKey(),
              name: typeof s.name === 'string' ? s.name : '',
              approver_type:
                typeof s.approver_type === 'string' ? s.approver_type : 'dynamic_manager',
              id: typeof s.id === 'number' ? s.id : undefined,
              specific_user_id:
                typeof s.specific_user_id === 'number' ? s.specific_user_id : null,
              specific_role: typeof s.specific_role === 'string' ? s.specific_role : null,
              condition: isRecord(s.condition) ? {
                field: typeof s.condition.field === 'string' ? s.condition.field : undefined,
                op: typeof s.condition.op === 'string' ? s.condition.op : undefined,
                value:
                  typeof s.condition.value === 'string' ||
                  typeof s.condition.value === 'number' ||
                  typeof s.condition.value === 'boolean'
                    ? s.condition.value
                    : null,
              } : null,
              parallel_group:
                typeof s.parallel_group === 'number' ? s.parallel_group : null,
              completion_policy:
                s.completion_policy === 'any' || s.completion_policy === 'all'
                  ? s.completion_policy
                  : 'all',
              escalation_days:
                typeof s.escalation_days === 'number' ? s.escalation_days : null,
              is_required: s.is_required !== false,
            };
            return step;
          })
      );
    } catch (error: unknown) {
      toast.error(getErrorMessage(error, t('workflows.loadError')));
      navigate('/settings/workflows');
    } finally {
      setLoading(false);
    }
  }, [workflowId, navigate, t]);

  useEffect(() => {
    void loadMeta().catch((error: unknown) => {
      toast.error(getErrorMessage(error, t('workflows.loadError')));
    });
  }, [loadMeta, t]);

  useEffect(() => {
    if (!isNew) {
      void loadWorkflow();
    }
  }, [isNew, loadWorkflow]);

  const entityOptions = useMemo(
    () => Object.entries(entityTypes).map(([value, label]) => ({ value, label })),
    [entityTypes]
  );

  const preferredApproverOptions = useMemo(() => {
    const preferred = [
      'dynamic_manager',
      'dynamic_skip_manager',
      'department_head',
      'role',
      'user',
      'hr',
      'cfo',
      'ceo',
    ];
    const opts = preferred
      .filter((k) => approverTypes[k])
      .map((value) => ({ value, label: approverTypes[value] }));
    // Mevcut adımlardaki legacy tipler de listede kalsın
    const used = new Set(steps.map((s) => s.approver_type));
    used.forEach((value) => {
      if (!opts.some((o) => o.value === value) && approverTypes[value]) {
        opts.push({ value, label: `${approverTypes[value]} (legacy)` });
      }
    });
    return opts;
  }, [approverTypes, steps]);

  const preview = useMemo(() => buildPreview(steps), [steps]);

  const updateStep = (index: number, patch: Partial<EditorStep>) => {
    setSteps((prev) => {
      const next = [...prev];
      next[index] = { ...next[index], ...patch };
      return next;
    });
  };

  const moveStep = (index: number, direction: -1 | 1) => {
    setSteps((prev) => {
      const target = index + direction;
      if (target < 0 || target >= prev.length) return prev;
      const next = [...prev];
      const tmp = next[index];
      next[index] = next[target];
      next[target] = tmp;
      return next;
    });
  };

  const removeStep = (index: number) => {
    setSteps((prev) => (prev.length <= 1 ? prev : prev.filter((_, i) => i !== index)));
  };

  const copyStep = (index: number) => {
    setSteps((prev) => {
      const clone: EditorStep = {
        ...prev[index],
        id: undefined,
        _key: newKey(),
        name: `${prev[index].name} (${t('workflows.copyStep')})`,
      };
      const next = [...prev];
      next.splice(index + 1, 0, clone);
      return next;
    });
  };

  const handleSave = async () => {
    if (!name.trim()) {
      toast.error(t('workflows.name'));
      return;
    }
    if (steps.length === 0) {
      toast.error(t('workflows.stepsRequired'));
      return;
    }

    const payloadSteps: WorkflowStepPayload[] = steps.map((s) => {
      const condition =
        s.condition?.field && (s.condition.op || s.condition.operator)
          ? {
              field: s.condition.field,
              op: s.condition.op ?? s.condition.operator,
              value:
                s.condition.value === '' || s.condition.value == null
                  ? null
                  : Number.isNaN(Number(s.condition.value))
                    ? s.condition.value
                    : Number(s.condition.value),
            }
          : null;

      return {
        id: s.id,
        name: s.name.trim() || t('workflows.stepName'),
        approver_type: s.approver_type,
        specific_user_id: isUser(s.approver_type)
          ? s.specific_user_id ?? null
          : null,
        specific_role:
          isRole(s.approver_type) && s.approver_type !== 'hr' && s.approver_type !== 'cfo' && s.approver_type !== 'ceo'
            ? s.specific_role ?? null
            : null,
        is_required: s.is_required !== false,
        can_skip: Boolean(s.can_skip),
        condition,
        parallel_group:
          s.parallel_group === null || s.parallel_group === undefined
            ? null
            : Number(s.parallel_group),
        completion_policy: s.parallel_group != null ? s.completion_policy ?? 'all' : 'all',
        escalation_days:
          s.escalation_days == null ? null : Number(s.escalation_days),
      };
    });

    const body = {
      name: name.trim(),
      entity_type: entityType,
      description: description.trim() || null,
      is_active: isActive,
      is_default: isDefault,
      escalation_days: escalationDays === '' ? null : Number(escalationDays),
      steps: payloadSteps,
    };

    try {
      setSaving(true);
      if (isNew) {
        const res = await workflowsApi.create(body);
        const created = res.data.data;
        toast.success(t('workflows.saveSuccess'));
        if (isRecord(created) && typeof created.id === 'number') {
          navigate(`/settings/workflows/${created.id}`);
        } else {
          navigate('/settings/workflows');
        }
      } else if (workflowId != null) {
        await workflowsApi.update(workflowId, body);
        toast.success(t('workflows.saveSuccess'));
        await loadWorkflow();
      }
    } catch (error: unknown) {
      toast.error(getErrorMessage(error, t('workflows.saveError')));
    } finally {
      setSaving(false);
    }
  };

  if (loading) {
    return (
      <div className="animate-fade-in">
        <div className="card">
          <div className="card-body text-center py-5">
            <div className="spinner-border" role="status" />
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="animate-fade-in">
      <div className="page-header">
        <div className="page-header-content">
          <h1 className="page-title">
            {isNew ? t('workflows.new') : t('workflows.edit')}
          </h1>
          <p className="page-subtitle">{t('workflows.listSubtitle')}</p>
        </div>
        <div className="page-header-actions" style={{ display: 'flex', gap: 'var(--space-2)' }}>
          <Link to="/settings/workflows" className="btn btn-secondary btn-sm">
            <BsArrowLeft /> {t('workflows.backToList')}
          </Link>
          <button
            type="button"
            className="btn btn-primary btn-sm"
            disabled={saving}
            onClick={() => void handleSave()}
          >
            <BsSave /> {t('workflows.save')}
          </button>
        </div>
      </div>

      <div
        style={{
          display: 'grid',
          gridTemplateColumns: 'minmax(0, 1fr) minmax(220px, 280px)',
          gap: 'var(--space-4)',
          alignItems: 'start',
        }}
      >
        <div>
          <div className="card" style={{ marginBottom: 'var(--space-4)' }}>
            <div className="card-body">
              <div className="form-group">
                <label className="form-label">{t('workflows.name')}</label>
                <input
                  className="form-control"
                  value={name}
                  onChange={(e) => setName(e.target.value)}
                />
              </div>
              <div className="form-group">
                <label className="form-label">{t('workflows.entityType')}</label>
                <Select
                  value={entityType}
                  onChange={setEntityType}
                  options={entityOptions}
                  disabled={!isNew}
                />
              </div>
              <div className="form-group">
                <label className="form-label">{t('workflows.description')}</label>
                <textarea
                  className="form-control"
                  rows={2}
                  value={description}
                  onChange={(e) => setDescription(e.target.value)}
                />
              </div>
              <div
                style={{
                  display: 'flex',
                  flexWrap: 'wrap',
                  gap: 'var(--space-4)',
                  alignItems: 'center',
                }}
              >
                <label style={{ display: 'flex', gap: 'var(--space-2)', alignItems: 'center' }}>
                  <input
                    type="checkbox"
                    checked={isActive}
                    onChange={(e) => setIsActive(e.target.checked)}
                  />
                  {t('workflows.active')}
                </label>
                <label style={{ display: 'flex', gap: 'var(--space-2)', alignItems: 'center' }}>
                  <input
                    type="checkbox"
                    checked={isDefault}
                    onChange={(e) => setIsDefault(e.target.checked)}
                  />
                  {t('workflows.default')}
                </label>
                <div className="form-group" style={{ margin: 0, minWidth: 160 }}>
                  <label className="form-label">{t('workflows.escalationDays')}</label>
                  <input
                    className="form-control"
                    type="number"
                    min={1}
                    value={escalationDays}
                    onChange={(e) => setEscalationDays(e.target.value)}
                  />
                </div>
              </div>
            </div>
          </div>

          <div className="card">
            <div
              className="card-header"
              style={{
                display: 'flex',
                justifyContent: 'space-between',
                alignItems: 'center',
              }}
            >
              <h3 className="card-title" style={{ margin: 0 }}>
                {t('workflows.steps')}
              </h3>
              <button
                type="button"
                className="btn btn-secondary btn-sm"
                onClick={() => setSteps((prev) => [...prev, emptyStep()])}
              >
                <BsPlus /> {t('workflows.addStep')}
              </button>
            </div>
            <div className="card-body" style={{ display: 'grid', gap: 'var(--space-3)' }}>
              {steps.map((step, index) => {
                const hasParallel = step.parallel_group != null;
                const cond = step.condition ?? { field: '', op: '', value: '' };

                return (
                  <div
                    key={step._key}
                    className="card"
                    style={{
                      border: '1px solid var(--border-subtle)',
                      background: 'var(--bg-elevated)',
                    }}
                  >
                    <div className="card-body" style={{ display: 'grid', gap: 'var(--space-3)' }}>
                      <div
                        style={{
                          display: 'flex',
                          justifyContent: 'space-between',
                          alignItems: 'center',
                          gap: 'var(--space-2)',
                        }}
                      >
                        <strong>
                          #{index + 1} {step.name || t('workflows.stepName')}
                        </strong>
                        <div style={{ display: 'flex', gap: 'var(--space-1)' }}>
                          <button
                            type="button"
                            className="btn btn-ghost btn-sm"
                            title={t('workflows.moveUp')}
                            onClick={() => moveStep(index, -1)}
                            disabled={index === 0}
                          >
                            <BsArrowUp />
                          </button>
                          <button
                            type="button"
                            className="btn btn-ghost btn-sm"
                            title={t('workflows.moveDown')}
                            onClick={() => moveStep(index, 1)}
                            disabled={index === steps.length - 1}
                          >
                            <BsArrowDown />
                          </button>
                          <button
                            type="button"
                            className="btn btn-ghost btn-sm"
                            title={t('workflows.copyStep')}
                            onClick={() => copyStep(index)}
                          >
                            <BsCopy />
                          </button>
                          <button
                            type="button"
                            className="btn btn-ghost btn-sm"
                            title={t('workflows.removeStep')}
                            onClick={() => removeStep(index)}
                            disabled={steps.length <= 1}
                          >
                            <BsTrash />
                          </button>
                        </div>
                      </div>

                      <div className="form-group" style={{ margin: 0 }}>
                        <label className="form-label">{t('workflows.stepName')}</label>
                        <input
                          className="form-control"
                          value={step.name}
                          onChange={(e) => updateStep(index, { name: e.target.value })}
                        />
                      </div>

                      <div className="form-group" style={{ margin: 0 }}>
                        <label className="form-label">{t('workflows.approverType')}</label>
                        <Select
                          value={step.approver_type}
                          onChange={(value) =>
                            updateStep(index, {
                              approver_type: value,
                              specific_user_id: null,
                              specific_role: null,
                            })
                          }
                          options={preferredApproverOptions}
                        />
                      </div>

                      {isUser(step.approver_type) ? (
                        <div className="form-group" style={{ margin: 0 }}>
                          <label className="form-label">{t('workflows.user')}</label>
                          <Select
                            value={
                              step.specific_user_id != null
                                ? String(step.specific_user_id)
                                : ''
                            }
                            onChange={(value) =>
                              updateStep(index, {
                                specific_user_id: value ? Number(value) : null,
                              })
                            }
                            options={users}
                            placeholder={t('workflows.selectUser')}
                            allowEmpty
                            emptyLabel={t('workflows.selectUser')}
                          />
                        </div>
                      ) : null}

                      {isRole(step.approver_type) &&
                      step.approver_type !== 'hr' &&
                      step.approver_type !== 'cfo' &&
                      step.approver_type !== 'ceo' ? (
                        <div className="form-group" style={{ margin: 0 }}>
                          <label className="form-label">{t('workflows.role')}</label>
                          <Select
                            value={step.specific_role ?? ''}
                            onChange={(value) =>
                              updateStep(index, { specific_role: value || null })
                            }
                            options={roles}
                            placeholder={t('workflows.selectRole')}
                            allowEmpty
                            emptyLabel={t('workflows.selectRole')}
                          />
                        </div>
                      ) : null}

                      {isDynamic(step.approver_type) ? (
                        <p style={{ margin: 0, color: 'var(--text-tertiary)', fontSize: '0.875rem' }}>
                          {t('workflows.dynamic')}: {approverTypes[step.approver_type]}
                        </p>
                      ) : null}

                      <div
                        style={{
                          display: 'grid',
                          gridTemplateColumns: '1fr 1fr 1fr',
                          gap: 'var(--space-2)',
                        }}
                      >
                        <div className="form-group" style={{ margin: 0 }}>
                          <label className="form-label">{t('workflows.conditionField')}</label>
                          <Select
                            value={cond.field ?? ''}
                            onChange={(value) =>
                              updateStep(index, {
                                condition: value
                                  ? {
                                      field: value,
                                      op: cond.op || '>',
                                      value: cond.value ?? '',
                                    }
                                  : null,
                              })
                            }
                            options={conditionFields.map((f) => ({ value: f, label: f }))}
                            allowEmpty
                            emptyLabel={t('workflows.noCondition')}
                          />
                        </div>
                        <div className="form-group" style={{ margin: 0 }}>
                          <label className="form-label">{t('workflows.conditionOp')}</label>
                          <Select
                            value={cond.op ?? ''}
                            onChange={(value) =>
                              updateStep(index, {
                                condition: {
                                  field: cond.field ?? 'total_days',
                                  op: value,
                                  value: cond.value ?? '',
                                },
                              })
                            }
                            options={conditionOps.map((op) => ({ value: op, label: op }))}
                            disabled={!cond.field}
                          />
                        </div>
                        <div className="form-group" style={{ margin: 0 }}>
                          <label className="form-label">{t('workflows.conditionValue')}</label>
                          <input
                            className="form-control"
                            value={
                              cond.value === null || cond.value === undefined
                                ? ''
                                : String(cond.value)
                            }
                            disabled={!cond.field}
                            onChange={(e) =>
                              updateStep(index, {
                                condition: {
                                  field: cond.field ?? 'total_days',
                                  op: cond.op || '>',
                                  value: e.target.value,
                                },
                              })
                            }
                          />
                        </div>
                      </div>

                      <div
                        style={{
                          display: 'grid',
                          gridTemplateColumns: '1fr 1fr 1fr',
                          gap: 'var(--space-2)',
                        }}
                      >
                        <div className="form-group" style={{ margin: 0 }}>
                          <label className="form-label">{t('workflows.parallelGroup')}</label>
                          <input
                            className="form-control"
                            type="number"
                            min={1}
                            placeholder={t('workflows.parallelHint')}
                            value={step.parallel_group ?? ''}
                            onChange={(e) =>
                              updateStep(index, {
                                parallel_group:
                                  e.target.value === '' ? null : Number(e.target.value),
                              })
                            }
                          />
                        </div>
                        {hasParallel ? (
                          <div className="form-group" style={{ margin: 0 }}>
                            <label className="form-label">
                              {t('workflows.completionPolicy')}
                            </label>
                            <Select
                              value={step.completion_policy ?? 'all'}
                              onChange={(value) =>
                                updateStep(index, {
                                  completion_policy: value === 'any' ? 'any' : 'all',
                                })
                              }
                              options={[
                                { value: 'all', label: t('workflows.completionAll') },
                                { value: 'any', label: t('workflows.completionAny') },
                              ]}
                            />
                          </div>
                        ) : (
                          <div />
                        )}
                        <div className="form-group" style={{ margin: 0 }}>
                          <label className="form-label">{t('workflows.stepEscalation')}</label>
                          <input
                            className="form-control"
                            type="number"
                            min={1}
                            value={step.escalation_days ?? ''}
                            onChange={(e) =>
                              updateStep(index, {
                                escalation_days:
                                  e.target.value === '' ? null : Number(e.target.value),
                              })
                            }
                          />
                        </div>
                      </div>
                    </div>
                  </div>
                );
              })}
            </div>
          </div>
        </div>

        <div className="card" style={{ position: 'sticky', top: 'var(--space-4)' }}>
          <div className="card-header">
            <h3 className="card-title">{t('workflows.preview')}</h3>
          </div>
          <div className="card-body">
            {preview ? (
              <p
                style={{
                  margin: 0,
                  fontFamily: 'var(--font-mono, ui-monospace, monospace)',
                  lineHeight: 1.6,
                  wordBreak: 'break-word',
                }}
              >
                {preview}
              </p>
            ) : (
              <p style={{ margin: 0, color: 'var(--text-tertiary)' }}>
                {t('workflows.previewEmpty')}
              </p>
            )}
          </div>
        </div>
      </div>
    </div>
  );
};

export default WorkflowEditorPage;
