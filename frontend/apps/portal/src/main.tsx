import React from 'react';
import ReactDOM from 'react-dom/client';
import '@shared/i18n';
import App from './App';
import '@shared/styles/theme.css';
import '@shared/styles/components.css';
import '@shared/styles/layout.css';
import './styles/portal.css';

ReactDOM.createRoot(document.getElementById('root')!).render(
  <React.StrictMode>
    <App />
  </React.StrictMode>
);

