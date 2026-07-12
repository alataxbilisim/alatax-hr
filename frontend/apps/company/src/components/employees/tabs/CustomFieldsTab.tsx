import React, { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { BsListCheck } from 'react-icons/bs';
import { CustomFieldRenderer, type CustomFieldDefinition } from '@shared/components';
import { customFieldsApi } from '@shared/services/api';
import type { CustomFieldValue } from '@shared/types/modules';

interface DefinedField extends CustomFieldDefinition {
  is_active?: boolean;
}

interface CustomFieldsTabProps {
  values?: Record<string, CustomFieldValue>;
}

const CustomFieldsTab: React.FC<CustomFieldsTabProps> = ({ values = {} }) => {
  const { t } = useTranslation('common');
  const [fields, setFields] = useState<CustomFieldDefinition[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    let cancelled = false;

    const load = async () => {
      try {
        setLoading(true);
        const response = await customFieldsApi.getAll('employee');
        const list = (response.data.data || []) as DefinedField[];
        if (!cancelled) {
          setFields(list.filter((f) => f.is_active !== false));
        }
      } catch {
        if (!cancelled) {
          setFields([]);
        }
      } finally {
        if (!cancelled) {
          setLoading(false);
        }
      }
    };

    void load();

    return () => {
      cancelled = true;
    };
  }, []);

  if (loading) {
    return (
      <div className="card">
        <div className="card-body text-center py-4">
          <div className="spinner-border spinner-border-sm" role="status" />
        </div>
      </div>
    );
  }

  if (fields.length === 0) {
    return (
      <div className="card">
        <div className="card-body">
          <p style={{ margin: 0, color: 'var(--text-tertiary)' }}>
            {t('customFields.empty')}
          </p>
        </div>
      </div>
    );
  }

  return (
    <div className="card">
      <div className="card-header">
        <h3 className="card-title">
          <BsListCheck style={{ marginRight: 'var(--sp-2)' }} />
          {t('customFields.title')}
        </h3>
      </div>
      <div className="card-body">
        <CustomFieldRenderer
          fields={fields}
          values={values}
          onChange={() => undefined}
          readonly
        />
      </div>
    </div>
  );
};

export default CustomFieldsTab;
