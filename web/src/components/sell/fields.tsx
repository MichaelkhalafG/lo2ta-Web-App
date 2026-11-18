"use client";

import { useId, type ReactNode } from "react";

/*
 * Controlled fields for the seller form (board 07: 52px fields, line-strong border, gold halo on focus, hint and
 * error tied to the field). Numbers are typed with Latin or Arabic digits and separators; the value is a number.
 */

const control =
  "h-control-lg w-full rounded-sm border border-line-strong bg-bg-1 px-4 text-text-1 outline-none transition-shadow duration-150 placeholder:text-text-3 focus:border-text-1 focus:shadow-[0_0_0_4px_var(--color-gold-tint)] aria-[invalid=true]:border-error";

const ARABIC_DIGITS: Record<string, string> = {
  "٠": "0", "١": "1", "٢": "2", "٣": "3", "٤": "4", "٥": "5", "٦": "6", "٧": "7", "٨": "8", "٩": "9",
  "۰": "0", "۱": "1", "۲": "2", "۳": "3", "۴": "4", "۵": "5", "۶": "6", "۷": "7", "۸": "8", "۹": "9",
};

/** Digits only (Arabic digits read as Latin); null when empty. */
export function parseWhole(text: string): number | null {
  const digits = text.replace(/[٠-٩۰-۹]/g, (d) => ARABIC_DIGITS[d] ?? d).replace(/[^0-9]/g, "");

  return digits === "" ? null : Number(digits.slice(0, 13));
}

/** A decimal with up to two places (sizes in m²). */
export function parseDecimal(text: string): string | null {
  const latin = text.replace(/[٠-٩۰-۹]/g, (d) => ARABIC_DIGITS[d] ?? d).replace(/[٫,]/g, ".").replace(/[^0-9.]/g, "");
  const match = /^\d{0,6}(\.\d{0,2})?/.exec(latin);

  return match && match[0] !== "" && match[0] !== "." ? match[0] : null;
}

export function formatMoney(value: number | null | undefined): string {
  return value === null || value === undefined ? "" : new Intl.NumberFormat("en-US").format(value);
}

export function FieldShell({
  id,
  label,
  required,
  hint,
  error,
  children,
}: {
  id: string;
  label: string;
  required?: boolean;
  hint?: string | undefined;
  error?: string | undefined;
  children: ReactNode;
}) {
  return (
    <div className="flex flex-col gap-2">
      <label htmlFor={id} className="text-small text-text-2">
        {label}
        {required ? (
          <span aria-hidden="true" className="ms-1 text-gold">
            *
          </span>
        ) : null}
      </label>
      {children}
      {hint ? (
        <p id={`${id}-hint`} className="text-small text-text-3">
          {hint}
        </p>
      ) : null}
      {error ? (
        <p id={`${id}-error`} role="alert" className="text-small text-error">
          {error}
        </p>
      ) : null}
    </div>
  );
}

function describedBy(id: string, hint?: string, error?: string): string | undefined {
  return [hint ? `${id}-hint` : null, error ? `${id}-error` : null].filter(Boolean).join(" ") || undefined;
}

export function TextInput({
  label,
  value,
  onChange,
  required,
  hint,
  error,
  dir,
  maxLength = 120,
  placeholder,
}: {
  label: string;
  value: string | null;
  onChange: (value: string) => void;
  required?: boolean;
  hint?: string | undefined;
  error?: string | undefined;
  dir?: "ltr" | "rtl";
  maxLength?: number;
  placeholder?: string;
}) {
  const id = useId();

  return (
    <FieldShell id={id} label={label} required={required} hint={hint} error={error}>
      <input
        id={id}
        value={value ?? ""}
        onChange={(e) => onChange(e.target.value)}
        dir={dir}
        maxLength={maxLength}
        placeholder={placeholder}
        aria-invalid={error ? true : undefined}
        aria-describedby={describedBy(id, hint, error)}
        className={control}
      />
    </FieldShell>
  );
}

/** Money in whole EGP, shown with separators, with the currency after the number. */
export function MoneyInput({
  label,
  value,
  onChange,
  required,
  hint,
  error,
  unit,
}: {
  label: string;
  value: number | null;
  onChange: (value: number | null) => void;
  required?: boolean;
  hint?: string | undefined;
  error?: string | undefined;
  unit: string;
}) {
  const id = useId();

  return (
    <FieldShell id={id} label={label} required={required} hint={hint} error={error}>
      {/* The number runs left to right, so its unit sits at the same end as the padding kept for it. */}
      <div className="relative" dir="ltr">
        <input
          id={id}
          inputMode="numeric"
          dir="ltr"
          value={formatMoney(value)}
          onChange={(e) => onChange(parseWhole(e.target.value))}
          aria-invalid={error ? true : undefined}
          aria-describedby={describedBy(id, hint, error)}
          className={`${control} pe-16 font-mono tabular`}
        />
        <span className="pointer-events-none absolute inset-y-0 end-4 flex items-center text-small text-text-3">{unit}</span>
      </div>
    </FieldShell>
  );
}

export function NumberInput({
  label,
  value,
  onChange,
  required,
  hint,
  error,
  suffix,
  decimal = false,
}: {
  label: string;
  value: number | string | null;
  onChange: (value: number | string | null) => void;
  required?: boolean;
  hint?: string | undefined;
  error?: string | undefined;
  suffix?: string;
  decimal?: boolean;
}) {
  const id = useId();

  return (
    <FieldShell id={id} label={label} required={required} hint={hint} error={error}>
      {/* The number runs left to right, so its unit sits at the same end as the padding kept for it. */}
      <div className="relative" dir="ltr">
        <input
          id={id}
          inputMode={decimal ? "decimal" : "numeric"}
          dir="ltr"
          value={value === null ? "" : String(value)}
          onChange={(e) => onChange(decimal ? parseDecimal(e.target.value) : parseWhole(e.target.value))}
          aria-invalid={error ? true : undefined}
          aria-describedby={describedBy(id, hint, error)}
          className={`${control} font-mono tabular ${suffix ? "pe-16" : ""}`}
        />
        {suffix ? <span className="pointer-events-none absolute inset-y-0 end-4 flex items-center text-small text-text-3">{suffix}</span> : null}
      </div>
    </FieldShell>
  );
}

export function SelectInput({
  label,
  value,
  onChange,
  options,
  required,
  hint,
  error,
  placeholder,
}: {
  label: string;
  value: string | null;
  onChange: (value: string | null) => void;
  options: { value: string; label: string; group?: string }[];
  required?: boolean;
  hint?: string | undefined;
  error?: string | undefined;
  placeholder: string;
}) {
  const id = useId();
  const groups = [...new Set(options.map((o) => o.group ?? ""))];

  return (
    <FieldShell id={id} label={label} required={required} hint={hint} error={error}>
      <select
        id={id}
        value={value ?? ""}
        onChange={(e) => onChange(e.target.value === "" ? null : e.target.value)}
        aria-invalid={error ? true : undefined}
        aria-describedby={describedBy(id, hint, error)}
        className={control}
      >
        <option value="">{placeholder}</option>
        {groups.map((group) =>
          group === "" ? (
            options.filter((o) => !o.group).map((o) => <option key={o.value} value={o.value}>{o.label}</option>)
          ) : (
            <optgroup key={group} label={group}>
              {options.filter((o) => o.group === group).map((o) => <option key={o.value} value={o.value}>{o.label}</option>)}
            </optgroup>
          ),
        )}
      </select>
    </FieldShell>
  );
}

/** One choice among a few, as pills (board 07: type, sub-type, finishing). */
export function Pills({
  label,
  value,
  onChange,
  options,
  required,
  error,
}: {
  label: string;
  value: string | null;
  onChange: (value: string) => void;
  options: { value: string; label: string }[];
  required?: boolean;
  error?: string | undefined;
}) {
  const id = useId();

  return (
    <fieldset className="flex flex-col gap-2" aria-describedby={error ? `${id}-error` : undefined}>
      <legend className="mb-2 text-small text-text-2">
        {label}
        {required ? (
          <span aria-hidden="true" className="ms-1 text-gold">
            *
          </span>
        ) : null}
      </legend>
      <div className="flex flex-wrap gap-2">
        {options.map((o) => (
          <label
            key={o.value}
            className={`inline-flex min-h-11 cursor-pointer items-center rounded-sm border px-4 text-body transition-colors duration-150 has-[:focus-visible]:shadow-[0_0_0_4px_var(--color-gold-tint)] ${value === o.value ? "border-text-1 bg-surface-2 text-text-1" : "border-line-strong text-text-2 hover:text-text-1"}`}
          >
            <input type="radio" name={id} value={o.value} checked={value === o.value} onChange={() => onChange(o.value)} className="sr-only" />
            {o.label}
          </label>
        ))}
      </div>
      {error ? (
        <p id={`${id}-error`} role="alert" className="text-small text-error">
          {error}
        </p>
      ) : null}
    </fieldset>
  );
}

export function YesNo({ label, value, onChange, yes, no, hint }: { label: string; value: boolean; onChange: (value: boolean) => void; yes: string; no: string; hint?: string }) {
  return (
    <div className="flex flex-col gap-2">
      <Pills label={label} value={value ? "1" : "0"} onChange={(v) => onChange(v === "1")} options={[{ value: "1", label: yes }, { value: "0", label: no }]} />
      {hint ? <p className="text-small text-text-3">{hint}</p> : null}
    </div>
  );
}

export function CheckList({
  label,
  values,
  onChange,
  options,
}: {
  label: string;
  values: string[];
  onChange: (values: string[]) => void;
  options: { value: string; label: string }[];
}) {
  return (
    <fieldset className="flex flex-col gap-2">
      <legend className="mb-2 text-small text-text-2">{label}</legend>
      <div className="grid gap-1 sm:grid-cols-2">
        {options.map((o) => (
          <label key={o.value} className="flex min-h-11 items-center gap-3 text-body text-text-1">
            <input
              type="checkbox"
              checked={values.includes(o.value)}
              onChange={(e) => onChange(e.target.checked ? [...values, o.value] : values.filter((v) => v !== o.value))}
              className="size-4 accent-[var(--color-text-1)]"
            />
            {o.label}
          </label>
        ))}
      </div>
    </fieldset>
  );
}

export function TextArea({
  label,
  value,
  onChange,
  required,
  hint,
  error,
  max,
  counter,
}: {
  label: string;
  value: string | null;
  onChange: (value: string) => void;
  required?: boolean;
  hint?: string | undefined;
  error?: string | undefined;
  max: number;
  counter: string;
}) {
  const id = useId();

  return (
    <FieldShell id={id} label={label} required={required} hint={hint} error={error}>
      <textarea
        id={id}
        value={value ?? ""}
        onChange={(e) => onChange(e.target.value)}
        maxLength={max}
        rows={7}
        aria-invalid={error ? true : undefined}
        aria-describedby={describedBy(id, hint, error)}
        className="w-full rounded-sm border border-line-strong bg-bg-1 p-4 text-body text-text-1 outline-none transition-shadow duration-150 focus:border-text-1 focus:shadow-[0_0_0_4px_var(--color-gold-tint)] aria-[invalid=true]:border-error"
      />
      <span className="self-end font-mono text-annotation text-text-3 tabular" dir="ltr">
        {counter}
      </span>
    </FieldShell>
  );
}

/** A numbered section inside a step (board 07.1: "3.1 العقد"). */
export function Section({ number, title, note, children }: { number: string; title: string; note?: string; children: ReactNode }) {
  return (
    <section className="flex flex-col gap-5 border-t border-line pt-6">
      <header className="flex items-baseline gap-3">
        <span className="font-mono text-annotation text-text-3" dir="ltr">
          {number}
        </span>
        <h2 className="text-h3">{title}</h2>
        {note ? <span className="text-small text-text-3">{note}</span> : null}
      </header>
      {children}
    </section>
  );
}
