"use client";

import { useLocale, useTranslations } from "next-intl";
import { useActionState, useState } from "react";
import { Link, usePathname } from "@/i18n/navigation";
import type { Account, Budget } from "@/lib/account";
import { budgetAction, logoutAction, profileAction, resendAction, type FormState } from "@/lib/actions/account";
import type { AreaTree, Choices, CompoundItem } from "@/lib/core";
import { Field, Notice, Submit } from "./Form";

const initial: FormState = {};

/** My account sub-navigation: overview, budget, favorites, sign out. */
export function AccountNav() {
  const t = useTranslations("account.nav");
  const locale = useLocale();
  const pathname = usePathname();
  const items = [
    { href: "/account", label: t("overview") },
    { href: "/account/budget", label: t("budget") },
    { href: "/account/favorites", label: t("favorites") },
  ];

  return (
    <nav aria-label={t("label")} className="flex flex-wrap items-center gap-1 border-b border-line">
      {items.map((item) => (
        <Link
          key={item.href}
          href={item.href}
          aria-current={pathname === item.href ? "page" : undefined}
          className={`relative px-3 py-3 text-body transition-colors duration-150 hover:text-text-1 ${pathname === item.href ? "text-text-1" : "text-text-2"}`}
        >
          {item.label}
          {pathname === item.href ? <span aria-hidden="true" className="absolute start-3 end-3 bottom-0 h-0.5 bg-text-1" /> : null}
        </Link>
      ))}
      <form action={logoutAction} className="ms-auto">
        <input type="hidden" name="locale" value={locale} />
        <button type="submit" className="px-3 py-3 text-small text-text-3 hover:text-text-1">
          {t("logout")}
        </button>
      </form>
    </nav>
  );
}

export function ResendVerification() {
  const t = useTranslations("account.overview");
  const locale = useLocale();
  const [state, action] = useActionState(resendAction, initial);

  return (
    <form action={action} className="flex flex-col gap-3">
      <input type="hidden" name="locale" value={locale} />
      {state.ok ? <Notice tone="success">{t("resent")}</Notice> : null}
      {state.ok === false ? <Notice tone="error">{state.errors?.email ?? state.message ?? t("resendFailed")}</Notice> : null}
      {state.ok ? null : (
        <button type="submit" className="self-start text-small text-text-1 underline underline-offset-4 hover:text-gold-bright">
          {t("resend")}
        </button>
      )}
    </form>
  );
}

export function ProfileForm({ account }: { account: Account }) {
  const t = useTranslations("account");
  const locale = useLocale();
  const [state, action] = useActionState(profileAction, initial);
  const e = state.errors ?? {};

  return (
    <form action={action} className="flex flex-col gap-5" noValidate>
      <input type="hidden" name="locale" value={locale} />
      {state.ok ? <Notice tone="success">{t("overview.saved")}</Notice> : null}
      <Field label={t("fields.name")} name="name" required defaultValue={state.values?.name ?? account.name} error={e.name} />
      <Field label={t("fields.phone")} name="phone" type="tel" dir="ltr" required defaultValue={state.values?.phone ?? account.phone} error={e.phone} />
      <div className="flex flex-col gap-2">
        <label htmlFor="preferred_locale" className="text-small text-text-2">
          {t("fields.language")}
        </label>
        <select
          id="preferred_locale"
          name="preferred_locale"
          defaultValue={state.values?.locale ?? account.locale}
          className="h-control-lg rounded-sm border border-line-strong bg-bg-1 px-4 text-text-1"
        >
          <option value="ar">العربية</option>
          <option value="en">English</option>
        </select>
        <p className="text-small text-text-3">{t("overview.languageHint")}</p>
      </div>
      <div className="max-w-xs">
        <Submit pendingLabel={t("pending")}>{t("overview.save")}</Submit>
      </div>
    </form>
  );
}

function Checkbox({ name, value, label, defaultChecked }: { name: string; value: string; label: string; defaultChecked: boolean }) {
  return (
    <label className="flex min-h-11 items-center gap-3 text-body text-text-1">
      <input type="checkbox" name={name} value={value} defaultChecked={defaultChecked} className="size-4 accent-[var(--color-text-1)]" />
      {label}
    </label>
  );
}

/**
 * The buyer budget (PLAN 2.3): what the buyer can pay and what they look for. Areas are grouped by governorate;
 * picking an area or a district is enough. Matching is the team's work; nothing here sends alerts.
 */
export function BudgetForm({
  budget,
  areas,
  compounds,
  options,
  maxAreas,
}: {
  budget: Budget | null;
  areas: AreaTree;
  compounds: CompoundItem[];
  options: Choices;
  maxAreas: number;
}) {
  const t = useTranslations("account.budget");
  const tf = useTranslations("account");
  const locale = useLocale();
  const [state, action] = useActionState(budgetAction, initial);
  const e = state.errors ?? {};
  const [areaFilter, setAreaFilter] = useState("");
  // After a failed save the typed values win over the stored budget, so nothing typed is lost.
  const v = state.values;
  const list = (key: string, stored: string[] | undefined) => (v ? (v[key] ?? "").split(",").filter(Boolean) : (stored ?? []));
  const pick = (key: string, stored: string | number | null | undefined) => (v ? (v[key] ?? "") : (stored?.toString() ?? ""));
  const chosenAreas = new Set(list("areas", budget?.areas));
  const chosenCompounds = new Set(list("compounds", budget?.compounds));
  const chosenTypes = v ? new Set(list("main_types", undefined)) : null;
  const match = (name: string) => areaFilter.trim() === "" || name.includes(areaFilter.trim());
  const areaError = e.areas ?? Object.entries(e).find(([k]) => k.startsWith("areas."))?.[1];

  return (
    <form action={action} className="flex flex-col gap-8" noValidate>
      <input type="hidden" name="locale" value={locale} />
      {state.ok ? <Notice tone="success">{t("saved")}</Notice> : null}
      {state.ok === false && Object.keys(e).length > 0 ? <Notice tone="error">{t("fix")}</Notice> : null}

      <fieldset className="grid gap-5 md:grid-cols-2">
        <legend className="mb-4 text-h3">{t("capacity")}</legend>
        <Field
          label={t("cash")}
          name="cash_available"
          inputMode="numeric"
          dir="ltr"
          required
          hint={t("cashHint")}
          defaultValue={pick("cash_available", budget?.cash_available)}
          error={e.cash_available}
        />
        <Field
          label={t("installment")}
          name="max_installment"
          inputMode="numeric"
          dir="ltr"
          required
          hint={t("installmentHint")}
          defaultValue={pick("max_installment", budget?.max_installment)}
          error={e.max_installment}
        />
        <div className="flex flex-col gap-2">
          <label htmlFor="installment_frequency" className="text-small text-text-2">
            {t("frequency")}
          </label>
          <select
            id="installment_frequency"
            name="installment_frequency"
            defaultValue={pick("installment_frequency", budget?.installment_frequency) || "quarterly"}
            className="h-control-lg rounded-sm border border-line-strong bg-bg-1 px-4 text-text-1"
          >
            {options.installment_frequencies.map((o) => (
              <option key={o.code} value={o.code}>
                {o.label}
              </option>
            ))}
          </select>
        </div>
        <Field
          label={t("target")}
          name="target_price"
          inputMode="numeric"
          dir="ltr"
          hint={t("targetHint")}
          defaultValue={pick("target_price", budget?.target_price)}
          error={e.target_price}
        />
      </fieldset>

      <fieldset className="flex flex-col gap-3">
        <legend className="mb-2 text-h3">{t("types")}</legend>
        <div className="flex flex-wrap gap-x-8">
          {options.main_types.map((o) => (
            <Checkbox key={o.code} name="main_types" value={o.code} label={o.label} defaultChecked={chosenTypes ? chosenTypes.has(o.code) : (budget?.main_types.includes(o.code) ?? o.code === "residential")} />
          ))}
        </div>
        {e.main_types ? <p role="alert" className="text-small text-error">{e.main_types}</p> : null}
      </fieldset>

      <fieldset className="flex flex-col gap-3">
        <legend className="mb-2 text-h3">{t("areas")}</legend>
        <p className="text-small text-text-3">{t("areasHint", { max: maxAreas })}</p>
        <input
          type="search"
          value={areaFilter}
          onChange={(event) => setAreaFilter(event.target.value)}
          placeholder={t("areasSearch")}
          aria-label={t("areasSearch")}
          className="h-control-md max-w-sm rounded-sm border border-line-strong bg-bg-1 px-4 text-text-1"
        />
        <div className="grid gap-4 md:grid-cols-2">
          {areas.map((gov) => {
            const shown = gov.areas.filter((a) => match(a.name) || a.districts.some((d) => match(d.name)));
            if (shown.length === 0) {
              return null;
            }

            return (
              <div key={gov.slug} className="rounded-sm border border-line p-4">
                <p className="mb-2 text-small text-text-3">{gov.name}</p>
                {shown.map((area) => (
                  <div key={area.slug}>
                    <Checkbox name="areas" value={area.slug} label={area.name} defaultChecked={chosenAreas.has(area.slug)} />
                    {area.districts.length > 0 ? (
                      <div className="ps-7">
                        {area.districts.filter((d) => match(d.name) || match(area.name)).map((d) => (
                          <Checkbox key={d.slug} name="areas" value={d.slug} label={d.name} defaultChecked={chosenAreas.has(d.slug)} />
                        ))}
                      </div>
                    ) : null}
                  </div>
                ))}
              </div>
            );
          })}
        </div>
        {areaError ? <p role="alert" className="text-small text-error">{areaError}</p> : null}
      </fieldset>

      {compounds.length > 0 ? (
        <fieldset className="flex flex-col gap-3">
          <legend className="mb-2 text-h3">{t("compounds")}</legend>
          <p className="text-small text-text-3">{t("compoundsHint")}</p>
          <div className="grid gap-x-8 md:grid-cols-3">
            {compounds.map((c) => (
              <Checkbox key={c.slug} name="compounds" value={c.slug} label={c.name} defaultChecked={chosenCompounds.has(c.slug)} />
            ))}
          </div>
        </fieldset>
      ) : null}

      <fieldset className="grid gap-5 md:grid-cols-2">
        <legend className="mb-4 text-h3">{t("more")}</legend>
        <Field
          label={t("bedrooms")}
          name="min_bedrooms"
          inputMode="numeric"
          dir="ltr"
          defaultValue={pick("min_bedrooms", budget?.min_bedrooms)}
          error={e.min_bedrooms}
        />
        <div className="flex flex-col gap-2">
          <span className="text-small text-text-2">{t("delivery")}</span>
          <div className="flex flex-wrap gap-x-6">
            {options.delivery_preferences.map((o) => (
              <label key={o.code} className="flex min-h-11 items-center gap-2 text-body">
                <input type="radio" name="delivery_pref" value={o.code} defaultChecked={(pick("delivery_pref", budget?.delivery_pref) || "either") === o.code} className="size-4 accent-[var(--color-text-1)]" />
                {o.label}
              </label>
            ))}
          </div>
        </div>
      </fieldset>

      <div className="max-w-xs">
        <Submit pendingLabel={tf("pending")}>{t("save")}</Submit>
      </div>
    </form>
  );
}
