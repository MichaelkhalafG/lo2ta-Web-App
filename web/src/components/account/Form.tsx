"use client";

import { useId, type InputHTMLAttributes, type ReactNode } from "react";
import { useFormStatus } from "react-dom";

/**
 * Form parts for the account pages (board 04, "Fields"): 52px fields on bg-1, a line-strong border, a gold halo on
 * focus, the hint and the error tied to the field for screen readers. Errors come from core in the page's language.
 */
export function Field({
  label,
  name,
  error,
  hint,
  required = false,
  ...input
}: { label: string; name: string; error?: string | undefined; hint?: string | undefined; required?: boolean } & Omit<
  InputHTMLAttributes<HTMLInputElement>,
  "name" | "required"
>) {
  const id = useId();
  const hintId = hint ? `${id}-hint` : undefined;
  const errorId = error ? `${id}-error` : undefined;

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
      <input
        id={id}
        name={name}
        required={required}
        aria-invalid={error ? true : undefined}
        aria-describedby={[hintId, errorId].filter(Boolean).join(" ") || undefined}
        className="h-control-lg w-full rounded-sm border border-line-strong bg-bg-1 px-4 text-text-1 outline-none transition-shadow duration-150 placeholder:text-text-3 focus:border-text-1 focus:shadow-[0_0_0_4px_var(--color-gold-tint)] aria-[invalid=true]:border-error"
        {...input}
      />
      {hint ? (
        <p id={hintId} className="text-small text-text-3">
          {hint}
        </p>
      ) : null}
      {error ? (
        <p id={errorId} role="alert" className="text-small text-error">
          {error}
        </p>
      ) : null}
    </div>
  );
}

export function Submit({ children, pendingLabel }: { children: string; pendingLabel: string }) {
  const { pending } = useFormStatus();

  return (
    <button
      type="submit"
      disabled={pending}
      aria-busy={pending}
      className="inline-flex h-control-lg w-full items-center justify-center rounded-sm bg-gold px-6 text-[1rem] font-semibold text-on-gold transition-colors duration-150 hover:bg-gold-bright disabled:opacity-60"
    >
      {pending ? pendingLabel : children}
    </button>
  );
}

export function Notice({ tone, children }: { tone: "error" | "success" | "info"; children: ReactNode }) {
  const colours = {
    error: "border-error/40 bg-error-tint text-error",
    success: "border-success/40 bg-success-tint text-success",
    info: "border-line bg-surface-1 text-text-2",
  }[tone];

  return (
    <div role={tone === "error" ? "alert" : "status"} className={`rounded-sm border px-4 py-3 text-small ${colours}`}>
      {children}
    </div>
  );
}

/** A field bots fill and people never see (Q18: honeypot first, Turnstile only if spam appears). */
export function Honeypot({ label }: { label: string }) {
  return (
    <div aria-hidden="true" className="absolute -start-[9999px] h-px w-px overflow-hidden">
      <label>
        {label}
        <input type="text" name="website" tabIndex={-1} autoComplete="off" defaultValue="" />
      </label>
    </div>
  );
}
