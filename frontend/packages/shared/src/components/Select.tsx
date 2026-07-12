import * as React from 'react';
import * as SelectPrimitive from '@radix-ui/react-select';
import { BsCheck, BsChevronDown, BsChevronUp, BsX } from 'react-icons/bs';

/**
 * Radix SelectItem value="" ÇALIŞMA ZAMANINDA HATA fırlatır.
 * Boş/temiz seçim için sentinel; dış API her zaman '' döner.
 */
export const SELECT_EMPTY_VALUE = '__ax_empty__';

export interface SelectOption {
  value: string;
  label: string;
  color?: string | null;
  disabled?: boolean;
}

export interface SelectProps {
  /** Seçili value (lookup referansı). Boş string = seçim yok (placeholder). */
  value?: string;
  onChange?: (value: string) => void;
  options: SelectOption[];
  placeholder?: string;
  disabled?: boolean;
  /** Geçersiz durum — border danger */
  error?: boolean;
  id?: string;
  name?: string;
  className?: string;
  /**
   * Boş seçeneği menüde göster (filtre "Tümü", opsiyonel alan "Seçiniz").
   * Item value asla "" olmaz — sentinel kullanılır; onChange('') üretir.
   */
  allowEmpty?: boolean;
  emptyLabel?: string;
  /**
   * Seçim varken trigger'da X ile temizle → onChange('').
   * allowEmpty olmadan da kullanılabilir (zorunlu alanlarda nadiren).
   */
  clearable?: boolean;
  /** İleride uzun listeler için; şimdilik kapalı iskelet */
  searchable?: boolean;
  'aria-label'?: string;
  'aria-labelledby'?: string;
}

function sanitizeOptions(options: SelectOption[]): SelectOption[] {
  return options.filter((opt) => opt.value !== '' && opt.value !== SELECT_EMPTY_VALUE);
}

/**
 * Ortak Select — Radix Select wrapper.
 * Lookup Engine yüzü: value referans tutar, label/color options'tan gelir.
 * RHF: Controller + value/onChange; yerel state aynı props.
 *
 * Boş değer sözleşmesi:
 * - Dışarı: value="" / onChange('') = seçim yok
 * - İçeri: SELECT_EMPTY_VALUE item + controlled mapping
 * - Opsiyonel submit: boş bırakılırsa ilk öğe GİTMEZ (kontrollü sentinel)
 */
export const Select = React.forwardRef<HTMLButtonElement, SelectProps>(
  (
    {
      value,
      onChange,
      options,
      placeholder = 'Seçiniz...',
      disabled = false,
      error = false,
      id,
      name,
      className,
      allowEmpty = false,
      emptyLabel,
      clearable = false,
      searchable: _searchable = false,
      'aria-label': ariaLabel,
      'aria-labelledby': ariaLabelledBy,
    },
    ref
  ) => {
    void _searchable;

    const safeOptions = React.useMemo(() => sanitizeOptions(options), [options]);
    const selected = safeOptions.find((o) => o.value === value);
    const isEmpty = value === undefined || value === '';
    const showClear = clearable && !disabled && !isEmpty;

    // allowEmpty yokken boş value → uncontrolled placeholder (ilk öğeyi seçme)
    const rootValue = isEmpty
      ? allowEmpty
        ? SELECT_EMPTY_VALUE
        : undefined
      : value;

    const items: SelectOption[] = allowEmpty
      ? [
          {
            value: SELECT_EMPTY_VALUE,
            label: emptyLabel ?? placeholder,
          },
          ...safeOptions,
        ]
      : safeOptions;

    const handleClear = (e: React.MouseEvent | React.KeyboardEvent) => {
      e.preventDefault();
      e.stopPropagation();
      onChange?.('');
    };

    return (
      <SelectPrimitive.Root
        value={rootValue}
        onValueChange={(v) => {
          if (v === SELECT_EMPTY_VALUE) {
            onChange?.('');
            return;
          }
          onChange?.(v);
        }}
        disabled={disabled}
        name={name}
      >
        <div className={['ax-select-wrap', showClear ? 'has-clear' : ''].filter(Boolean).join(' ')}>
          <SelectPrimitive.Trigger
            ref={ref}
            id={id}
            className={['ax-select-trigger', error ? 'is-invalid' : '', className]
              .filter(Boolean)
              .join(' ')}
            aria-label={ariaLabel}
            aria-labelledby={ariaLabelledBy}
            title={selected?.label || undefined}
          >
            <span className="ax-select-trigger-inner">
              {selected?.color ? (
                <span
                  className="ax-select-swatch"
                  style={{ background: selected.color }}
                  aria-hidden
                />
              ) : null}
              <span className="ax-select-value-text">
                <SelectPrimitive.Value placeholder={placeholder} />
              </span>
            </span>
            <SelectPrimitive.Icon className="ax-select-icon" asChild>
              <BsChevronDown aria-hidden />
            </SelectPrimitive.Icon>
          </SelectPrimitive.Trigger>

          {showClear ? (
            <button
              type="button"
              className="ax-select-clear"
              aria-label="Seçimi temizle"
              tabIndex={0}
              onClick={handleClear}
              onKeyDown={(e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                  handleClear(e);
                }
              }}
            >
              <BsX aria-hidden />
            </button>
          ) : null}
        </div>

        <SelectPrimitive.Portal>
          <SelectPrimitive.Content
            className="ax-select-content"
            position="popper"
            sideOffset={4}
            collisionPadding={8}
          >
            <SelectPrimitive.ScrollUpButton className="ax-select-scroll-btn">
              <BsChevronUp aria-hidden />
            </SelectPrimitive.ScrollUpButton>
            <SelectPrimitive.Viewport className="ax-select-viewport">
              {items.map((opt) => (
                <SelectPrimitive.Item
                  key={opt.value}
                  value={opt.value}
                  disabled={opt.disabled}
                  className="ax-select-item"
                  title={opt.label}
                >
                  <span className="ax-select-item-inner">
                    {opt.color ? (
                      <span
                        className="ax-select-swatch"
                        style={{ background: opt.color }}
                        aria-hidden
                      />
                    ) : null}
                    <SelectPrimitive.ItemText>{opt.label}</SelectPrimitive.ItemText>
                  </span>
                  <SelectPrimitive.ItemIndicator className="ax-select-item-check">
                    <BsCheck aria-hidden />
                  </SelectPrimitive.ItemIndicator>
                </SelectPrimitive.Item>
              ))}
            </SelectPrimitive.Viewport>
            <SelectPrimitive.ScrollDownButton className="ax-select-scroll-btn">
              <BsChevronDown aria-hidden />
            </SelectPrimitive.ScrollDownButton>
          </SelectPrimitive.Content>
        </SelectPrimitive.Portal>
      </SelectPrimitive.Root>
    );
  }
);

Select.displayName = 'Select';
