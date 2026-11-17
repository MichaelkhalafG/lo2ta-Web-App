"use client";

import { useLocale, useTranslations } from "next-intl";
import { useActionState } from "react";
import { Link } from "@/i18n/navigation";
import { forgotAction, loginAction, registerAction, resetAction, type FormState } from "@/lib/actions/account";
import { returnQuery } from "@/lib/intent";
import { Field, Honeypot, Notice, Submit } from "./Form";

const initial: FormState = {};

function Hidden({ next, intent }: { next: string; intent: string | null }) {
  const locale = useLocale();

  return (
    <>
      <input type="hidden" name="locale" value={locale} />
      <input type="hidden" name="next" value={next} />
      {intent ? <input type="hidden" name="intent" value={intent} /> : null}
    </>
  );
}

export function LoginForm({ next, intent, resetDone }: { next: string; intent: string | null; resetDone: boolean }) {
  const t = useTranslations("account");
  const [state, action] = useActionState(loginAction, initial);

  return (
    <form action={action} className="flex flex-col gap-5" noValidate>
      <Hidden next={next} intent={intent} />
      {resetDone ? <Notice tone="success">{t("login.resetDone")}</Notice> : null}
      {intent ? <Notice tone="info">{t("login.why")}</Notice> : null}
      {state.message && !state.errors?.email ? <Notice tone="error">{state.message}</Notice> : null}
      <Field
        label={t("fields.email")}
        name="email"
        type="email"
        dir="ltr"
        autoComplete="username"
        required
        defaultValue={state.values?.email ?? ""}
        error={state.errors?.email}
      />
      <Field label={t("fields.password")} name="password" type="password" dir="ltr" autoComplete="current-password" required error={state.errors?.password} />
      <Link href="/forgot-password" className="self-start text-small text-text-2 underline underline-offset-4 hover:text-text-1">
        {t("login.forgot")}
      </Link>
      <Submit pendingLabel={t("pending")}>{t("login.submit")}</Submit>
      <p className="text-small text-text-2">
        {t("login.noAccount")}{" "}
        <Link href={`/register?${returnQuery(next, intent)}`} className="text-text-1 underline underline-offset-4">
          {t("login.register")}
        </Link>
      </p>
    </form>
  );
}

export function RegisterForm({ next, intent }: { next: string; intent: string | null }) {
  const t = useTranslations("account");
  const [state, action] = useActionState(registerAction, initial);
  const e = state.errors ?? {};

  return (
    <form action={action} className="relative flex flex-col gap-5" noValidate>
      <Hidden next={next} intent={intent} />
      <Honeypot label={t("fields.website")} />
      {state.message && Object.keys(e).length === 0 ? <Notice tone="error">{state.message}</Notice> : null}
      <Field label={t("fields.name")} name="name" autoComplete="name" required defaultValue={state.values?.name ?? ""} error={e.name} />
      <Field
        label={t("fields.email")}
        name="email"
        type="email"
        dir="ltr"
        autoComplete="email"
        required
        hint={t("register.emailHint")}
        defaultValue={state.values?.email ?? ""}
        error={e.email}
      />
      <Field
        label={t("fields.phone")}
        name="phone"
        type="tel"
        dir="ltr"
        inputMode="tel"
        autoComplete="tel"
        required
        placeholder="01012345678"
        hint={t("register.phoneHint")}
        defaultValue={state.values?.phone ?? ""}
        error={e.phone}
      />
      <Field
        label={t("fields.password")}
        name="password"
        type="password"
        dir="ltr"
        autoComplete="new-password"
        required
        minLength={8}
        hint={t("register.passwordHint")}
        error={e.password}
      />
      <Field label={t("fields.passwordConfirm")} name="password_confirmation" type="password" dir="ltr" autoComplete="new-password" required />
      <div className="flex flex-col gap-2">
        <label className="flex items-start gap-3 text-small text-text-2">
          <input type="checkbox" name="terms" required className="mt-1 size-4 accent-[var(--color-text-1)]" aria-invalid={e.terms ? true : undefined} />
          <span>
            {t.rich("register.terms", {
              terms: (chunks) => (
                <Link href="/terms" className="text-text-1 underline underline-offset-4" target="_blank">
                  {chunks}
                </Link>
              ),
              privacy: (chunks) => (
                <Link href="/privacy" className="text-text-1 underline underline-offset-4" target="_blank">
                  {chunks}
                </Link>
              ),
            })}
          </span>
        </label>
        {e.terms ? (
          <p role="alert" className="text-small text-error">
            {e.terms}
          </p>
        ) : null}
      </div>
      {e.website ? <Notice tone="error">{e.website}</Notice> : null}
      <Submit pendingLabel={t("pending")}>{t("register.submit")}</Submit>
      <p className="text-small text-text-2">
        {t("register.haveAccount")}{" "}
        <Link href={`/login?${returnQuery(next, intent)}`} className="text-text-1 underline underline-offset-4">
          {t("register.login")}
        </Link>
      </p>
    </form>
  );
}

export function ForgotForm() {
  const t = useTranslations("account");
  const locale = useLocale();
  const [state, action] = useActionState(forgotAction, initial);

  if (state.ok) {
    return <Notice tone="success">{t("forgot.sent", { email: state.values?.email ?? "" })}</Notice>;
  }

  return (
    <form action={action} className="flex flex-col gap-5" noValidate>
      <input type="hidden" name="locale" value={locale} />
      {state.message && !state.errors?.email ? <Notice tone="error">{state.message}</Notice> : null}
      <Field label={t("fields.email")} name="email" type="email" dir="ltr" autoComplete="email" required defaultValue={state.values?.email ?? ""} error={state.errors?.email} />
      <Submit pendingLabel={t("pending")}>{t("forgot.submit")}</Submit>
      <Link href="/login" className="text-small text-text-2 underline underline-offset-4 hover:text-text-1">
        {t("forgot.back")}
      </Link>
    </form>
  );
}

export function ResetForm({ token, email }: { token: string; email: string }) {
  const t = useTranslations("account");
  const locale = useLocale();
  const [state, action] = useActionState(resetAction, initial);
  const e = state.errors ?? {};

  return (
    <form action={action} className="flex flex-col gap-5" noValidate>
      <input type="hidden" name="locale" value={locale} />
      <input type="hidden" name="token" value={token} />
      {e.email ? <Notice tone="error">{e.email}</Notice> : null}
      <Field label={t("fields.email")} name="email" type="email" dir="ltr" autoComplete="username" required defaultValue={state.values?.email ?? email} />
      <Field label={t("fields.newPassword")} name="password" type="password" dir="ltr" autoComplete="new-password" required minLength={8} hint={t("register.passwordHint")} error={e.password} />
      <Field label={t("fields.passwordConfirm")} name="password_confirmation" type="password" dir="ltr" autoComplete="new-password" required />
      <Submit pendingLabel={t("pending")}>{t("reset.submit")}</Submit>
    </form>
  );
}
