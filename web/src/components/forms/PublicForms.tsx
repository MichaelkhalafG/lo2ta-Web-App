"use client";

import { useLocale, useTranslations } from "next-intl";
import { useActionState } from "react";
import { contactAction, partnershipAction, type FormResult } from "@/lib/actions/requests";
import { Field, Honeypot, Notice, Submit } from "../account/Form";

/*
 * The contact and partnership forms (PLAN 2.6; Michael 2026-11-22: minimal fields, no automatic e-mail to the
 * sender, a thank-you on screen). Core checks everything, limits by IP and drops what a bot sends.
 */

const initial: FormResult = { ok: false };

function MessageField({ label, name, error, defaultValue, max }: { label: string; name: string; error?: string | undefined; defaultValue?: string; max: number }) {
  return (
    <label className="flex flex-col gap-2 text-small text-text-2">
      <span>
        {label}
        <span aria-hidden="true" className="ms-1 text-gold">
          *
        </span>
      </span>
      <textarea
        name={name}
        required
        maxLength={max}
        rows={6}
        defaultValue={defaultValue}
        aria-invalid={error ? true : undefined}
        className="rounded-sm border border-line-strong bg-bg-1 p-4 text-body text-text-1 outline-none focus:border-text-1 focus:shadow-[0_0_0_4px_var(--color-gold-tint)] aria-[invalid=true]:border-error"
      />
      {error ? (
        <span role="alert" className="text-error">
          {error}
        </span>
      ) : null}
    </label>
  );
}

export function ContactForm({ max }: { max: number }) {
  const t = useTranslations("forms");
  const locale = useLocale();
  const [state, action] = useActionState(contactAction, initial);
  const e = state.errors ?? {};
  const v = state.values ?? {};

  if (state.ok) {
    return <Notice tone="success">{t("thanks")}</Notice>;
  }

  return (
    <form action={action} className="flex max-w-xl flex-col gap-5" noValidate>
      <input type="hidden" name="locale" value={locale} />
      <Honeypot label="website" />
      {state.message && Object.keys(e).length === 0 ? <Notice tone="error">{state.message}</Notice> : null}
      <Field label={t("name")} name="name" required defaultValue={v.name} error={e.name} autoComplete="name" />
      <Field label={t("email")} name="email" type="email" dir="ltr" required defaultValue={v.email} error={e.email} autoComplete="email" />
      <Field label={t("phoneOptional")} name="phone" type="tel" dir="ltr" defaultValue={v.phone} error={e.phone} autoComplete="tel" />
      <MessageField label={t("message")} name="message" defaultValue={v.message} error={e.message} max={max} />
      <div className="max-w-xs">
        <Submit pendingLabel={t("sending")}>{t("send")}</Submit>
      </div>
    </form>
  );
}

export function PartnershipForm({ max }: { max: number }) {
  const t = useTranslations("forms");
  const locale = useLocale();
  const [state, action] = useActionState(partnershipAction, initial);
  const e = state.errors ?? {};
  const v = state.values ?? {};

  if (state.ok) {
    return <Notice tone="success">{t("thanksPartner")}</Notice>;
  }

  return (
    <form action={action} className="flex max-w-xl flex-col gap-5" noValidate>
      <input type="hidden" name="locale" value={locale} />
      <Honeypot label="website" />
      {state.message && Object.keys(e).length === 0 ? <Notice tone="error">{state.message}</Notice> : null}
      <Field label={t("company")} name="company" required defaultValue={v.company} error={e.company} autoComplete="organization" />
      <Field label={t("contactName")} name="contact_name" required defaultValue={v.contact_name} error={e.contact_name} autoComplete="name" />
      <Field label={t("jobTitleOptional")} name="job_title" defaultValue={v.job_title} error={e.job_title} autoComplete="organization-title" />
      <Field label={t("email")} name="email" type="email" dir="ltr" required defaultValue={v.email} error={e.email} autoComplete="email" />
      <Field label={t("phone")} name="phone" type="tel" dir="ltr" required defaultValue={v.phone} error={e.phone} autoComplete="tel" />
      <Field label={t("projectsOptional")} name="projects_count" inputMode="numeric" dir="ltr" defaultValue={v.projects_count} error={e.projects_count} />
      <MessageField label={t("message")} name="message" defaultValue={v.message} error={e.message} max={max} />
      <div className="max-w-xs">
        <Submit pendingLabel={t("sending")}>{t("send")}</Submit>
      </div>
    </form>
  );
}
