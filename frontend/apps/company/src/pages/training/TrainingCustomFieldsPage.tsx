import React from 'react';
import ModuleCustomFieldsPage from '../shared/ModuleCustomFieldsPage';

const TrainingCustomFieldsPage: React.FC = () => {
  return (
    <ModuleCustomFieldsPage
      entityType="training"
      moduleLabel="Eğitim Yönetimi"
      backPath="/training"
      moduleColor="#f97316"
    />
  );
};

export default TrainingCustomFieldsPage;

