import React from 'react';
import ModuleCustomFieldsPage from '../shared/ModuleCustomFieldsPage';

const RecruitmentCustomFieldsPage: React.FC = () => {
  return (
    <ModuleCustomFieldsPage
      entityType="job_application"
      moduleLabel="İşe Alım"
      backPath="/recruitment/applications"
      moduleColor="#f59e0b"
    />
  );
};

export default RecruitmentCustomFieldsPage;

