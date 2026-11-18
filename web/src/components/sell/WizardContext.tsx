"use client";

import { useLocale } from "next-intl";
import { createContext, useCallback, useContext, useEffect, useMemo, useRef, useState, type ReactNode } from "react";
import { useRouter } from "@/i18n/navigation";
import type { Locale } from "@/i18n/routing";
import type { FieldErrors } from "@/lib/account";
import { saveStepAction } from "@/lib/actions/sell";
import type { AreaTree, CompoundItem } from "@/lib/core";
import type { DeveloperItem, FeatureItem, SellChoices, SellerUnit } from "@/lib/sell";

/*
 * The wizard's shared state: the unit as core last answered it (readiness, the live exit report), the "saved"
 * stamp, and the reference lists. Every step autosaves through `useStep`, a short while after the last change,
 * and flushes before leaving.
 */

export type Reference = {
  developers: DeveloperItem[];
  compounds: CompoundItem[];
  areas: AreaTree;
  features: FeatureItem[];
  choices: SellChoices;
  settings: Record<string, number | boolean | string>;
  /** The seller site texts (client texts, edited in the dashboard): notices, no-over rule, declarations. */
  texts: Record<string, string | null>;
};

type SaveStatus = { kind: "idle" } | { kind: "saving" } | { kind: "saved"; at: Date } | { kind: "error" };

type WizardValue = {
  unit: SellerUnit;
  setUnit: (unit: SellerUnit) => void;
  status: SaveStatus;
  setStatus: (status: SaveStatus) => void;
  reference: Reference;
  locale: Locale;
  registerFlush: (flush: (() => Promise<boolean>) | null) => void;
  flush: () => Promise<boolean>;
};

const Context = createContext<WizardValue | null>(null);

export function WizardProvider({ unit: initial, reference, children }: { unit: SellerUnit; reference: Reference; children: ReactNode }) {
  const locale = useLocale() as Locale;
  const [unit, setUnit] = useState(initial);
  const [status, setStatus] = useState<SaveStatus>({ kind: "idle" });
  const flushRef = useRef<(() => Promise<boolean>) | null>(null);
  const registerFlush = useCallback((flush: (() => Promise<boolean>) | null) => {
    flushRef.current = flush;
  }, []);
  const flush = useCallback(async () => (flushRef.current ? flushRef.current() : true), []);

  const value = useMemo(
    () => ({ unit, setUnit, status, setStatus, reference, locale, registerFlush, flush }),
    [unit, status, reference, locale, registerFlush, flush],
  );

  return <Context.Provider value={value}>{children}</Context.Provider>;
}

export function useWizard(): WizardValue {
  const value = useContext(Context);
  if (!value) {
    throw new Error("useWizard outside WizardProvider");
  }

  return value;
}

const DELAY_MS = 800;

/**
 * One step's form state with autosave. `set` changes a field and schedules a save; `next` saves with the step
 * check and moves on when core accepts it. Typed values are never lost: core stores them before it checks.
 */
export function useStep<T extends Record<string, unknown>>(step: number, initial: T) {
  const { unit, setUnit, setStatus, locale, registerFlush } = useWizard();
  const router = useRouter();
  const [values, setValues] = useState<T>(initial);
  const [errors, setErrors] = useState<FieldErrors>({});
  const [message, setMessage] = useState<string | null>(null);
  const latest = useRef<T>(initial);
  const timer = useRef<ReturnType<typeof setTimeout> | null>(null);
  const dirty = useRef(false);

  const persist = useCallback(
    async (complete: boolean): Promise<boolean> => {
      if (timer.current) {
        clearTimeout(timer.current);
        timer.current = null;
      }
      if (!complete && !dirty.current) {
        return true;
      }
      dirty.current = false;
      setStatus({ kind: "saving" });
      const result = await saveStepAction(unit.id, step, latest.current, complete, locale);
      if (result.ok && result.unit) {
        setUnit(result.unit);
        setErrors({});
        setMessage(null);
        setStatus({ kind: "saved", at: new Date() });

        return true;
      }
      // A failed step check still saved the values (core stores before it checks).
      setStatus(result.status === 422 && complete ? { kind: "saved", at: new Date() } : { kind: "error" });
      setErrors(result.errors ?? {});
      setMessage(result.message ?? null);

      return false;
    },
    [unit.id, step, locale, setUnit, setStatus],
  );

  useEffect(() => {
    registerFlush(() => persist(false));

    return () => registerFlush(null);
  }, [persist, registerFlush]);

  function set<K extends keyof T>(key: K, value: T[K]): void {
    const next = { ...latest.current, [key]: value };
    latest.current = next;
    dirty.current = true;
    setValues(next);
    if (timer.current) {
      clearTimeout(timer.current);
    }
    timer.current = setTimeout(() => void persist(false), DELAY_MS);
  }

  async function next(target: string): Promise<void> {
    if (await persist(true)) {
      router.push(target);
    }
  }

  return { values, set, errors, message, next, persist };
}
