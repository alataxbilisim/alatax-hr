/**
 * Lookup Engine — yönetim UI tip kataloğu.
 * LookupService TYPE_* / SYSTEM_TYPES / HYBRID_TYPES ile hizalı; etiketler Türkçe.
 */

export type LookupTypeKind = 'firm' | 'system' | 'hybrid';

export interface LookupTypeDef {
  type: string;
  label: string;
  kind: LookupTypeKind;
}

export interface LookupTypeGroup {
  id: string;
  label: string;
  types: LookupTypeDef[];
}

/** Sistem: salt okunur (CRUD yok) */
export const SYSTEM_LOOKUP_TYPES: readonly string[] = [
  'currency',
  'city_tr',
  'blood_type',
  'country',
  'document_file_type',
  'survey_question_type',
] as const;

/** Hibrit: value/silme kilitli; label/renk/sıra/aktif düzenlenebilir */
export const HYBRID_LOOKUP_TYPES: readonly string[] = [
  'leave_request_status',
  'leave_gender_restriction',
  'application_stage',
  'interview_status',
  'expense_claim_status',
  'employee_request_status',
  'performance_period_status',
  'performance_review_status',
  'onboarding_process_status',
  'onboarding_task_status',
  'document_approval_status',
  'employee_document_status',
  'training_session_status',
] as const;

export function getLookupTypeKind(lookupType: string): LookupTypeKind {
  if (SYSTEM_LOOKUP_TYPES.includes(lookupType)) return 'system';
  if (HYBRID_LOOKUP_TYPES.includes(lookupType)) return 'hybrid';
  return 'firm';
}

function def(type: string, label: string): LookupTypeDef {
  return { type, label, kind: getLookupTypeKind(type) };
}

/** Sol panel grupları — bilinen LookupService tipleri */
export const LOOKUP_TYPE_GROUPS: LookupTypeGroup[] = [
  {
    id: 'personnel',
    label: 'Personel',
    types: [
      def('employee_status', 'Çalışan durumu'),
      def('work_type', 'Çalışma tipi'),
      def('gender', 'Cinsiyet'),
      def('marital_status', 'Medeni durum'),
      def('education_level', 'Eğitim seviyesi'),
      def('emergency_relation', 'Acil durum yakınlığı'),
      def('contract_type', 'Sözleşme tipi'),
      def('employee_document_category', 'Personel evrak kategorisi'),
      def('blood_type', 'Kan grubu'),
    ],
  },
  {
    id: 'leave',
    label: 'İzin',
    types: [
      def('leave_request_status', 'İzin talep durumu'),
      def('leave_gender_restriction', 'İzin cinsiyet kısıtı'),
      def('holiday_type', 'Tatil tipi'),
    ],
  },
  {
    id: 'recruitment',
    label: 'İşe Alım',
    types: [
      def('application_stage', 'Başvuru aşaması'),
      def('experience_level', 'Deneyim seviyesi'),
      def('job_position_status', 'Pozisyon durumu'),
      def('interview_type', 'Mülakat tipi'),
      def('interview_status', 'Mülakat durumu'),
      def('interview_recommendation', 'Mülakat önerisi'),
    ],
  },
  {
    id: 'assets',
    label: 'Varlık',
    types: [
      def('asset_status', 'Varlık durumu'),
      def('asset_condition', 'Varlık koşulu'),
    ],
  },
  {
    id: 'expenses_requests',
    label: 'Masraf & Talep',
    types: [
      def('expense_claim_status', 'Masraf talep durumu'),
      def('employee_request_priority', 'Talep önceliği'),
      def('employee_request_status', 'Talep durumu'),
      def('currency', 'Para birimi'),
    ],
  },
  {
    id: 'performance',
    label: 'Performans',
    types: [
      def('performance_period_status', 'Dönem durumu'),
      def('performance_review_status', 'Değerlendirme durumu'),
      def('continuous_feedback_type', 'Sürekli geri bildirim tipi'),
    ],
  },
  {
    id: 'onboarding',
    label: 'İşe Alıştırma',
    types: [
      def('onboarding_process_status', 'Süreç durumu'),
      def('onboarding_task_status', 'Görev durumu'),
    ],
  },
  {
    id: 'documents',
    label: 'Evrak',
    types: [
      def('document_approval_status', 'Belge onay durumu'),
      def('document_file_type', 'Dosya tipi'),
      def('employee_document_status', 'Personel evrak durumu'),
    ],
  },
  {
    id: 'training',
    label: 'Eğitim',
    types: [
      def('training_type', 'Eğitim tipi'),
      def('training_category', 'Eğitim kategorisi'),
      def('training_session_status', 'Oturum durumu'),
    ],
  },
  {
    id: 'surveys',
    label: 'Anket',
    types: [
      def('survey_type', 'Anket tipi'),
      def('survey_question_type', 'Soru tipi'),
    ],
  },
  {
    id: 'geography',
    label: 'Coğrafya',
    types: [
      def('country', 'Ülke'),
      def('city_tr', 'Türkiye illeri'),
    ],
  },
];

export function findLookupTypeDef(lookupType: string): LookupTypeDef | undefined {
  for (const group of LOOKUP_TYPE_GROUPS) {
    const found = group.types.find((t) => t.type === lookupType);
    if (found) return found;
  }
  return undefined;
}

export const DEFAULT_LOOKUP_TYPE =
  LOOKUP_TYPE_GROUPS[0]?.types[0]?.type ?? 'employee_status';
