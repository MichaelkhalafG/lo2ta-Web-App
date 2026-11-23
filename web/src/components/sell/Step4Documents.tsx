"use client";

import { useTranslations } from "next-intl";
import { useId, useState } from "react";
import { deleteDocumentAction, refreshUnitAction } from "@/lib/actions/sell";
import { prepareImage, SCAN_MAX_SIDE, uploadWithProgress } from "@/lib/imagePrep";
import { problemsOf, type SellerDocument } from "@/lib/sell";
import { StepNav, WizardFrame } from "./WizardFrame";
import { useStep, useWizard } from "./WizardContext";

/*
 * Step 4, the documents (PLAN 2.2.2, 2.2.14, 2.2.15; board 07.2). Each type is a slot; image scans shrink in the
 * browser first; "Next" waits until every upload has finished (PLAN 2.2.13 point 8). The files are private: only the
 * seller and the review team ever see them.
 */

const SLOTS = [
  { type: "contract", required: true, setting: "doc_max_contract" },
  { type: "schedule", required: true, setting: "doc_max_schedule" },
  { type: "national_id_front", required: true, setting: null },
  { type: "national_id_back", required: true, setting: null },
  { type: "receipt", required: false, setting: "doc_max_receipt" },
  { type: "statement", required: false, setting: "doc_max_statement" },
] as const;

function size(bytes: number): string {
  return bytes >= 1024 * 1024 ? `${(bytes / 1024 / 1024).toFixed(1)} MB` : `${Math.max(1, Math.round(bytes / 1024))} KB`;
}

/** Keys for the upload rows: unique within the page, nothing more. */
let uploadSeq = 0;
function nextUploadKey(): string {
  uploadSeq += 1;

  return `upload-${uploadSeq}`;
}

export type Upload = { key: string; type: string; name: string; percent: number };

/**
 * The six document slots with upload, progress and removal. The parent owns the upload list, so a step can wait for it.
 * Used by step 4 and by the unit page in My account (new receipts after submit, PLAN 2.2.7).
 */
export function DocumentSlots({
  uploads,
  setUploads,
  errors = {},
}: {
  uploads: Upload[];
  setUploads: (update: (u: Upload[]) => Upload[]) => void;
  errors?: Record<string, string>;
}) {
  const t = useTranslations("sell.step4");
  const { unit, setUnit, reference, locale } = useWizard();
  const [failures, setFailures] = useState<Record<string, string>>({});
  const receiptsRequired = reference.settings.receipts_required === true;
  const maxMb = typeof reference.settings.upload_max_mb === "number" ? reference.settings.upload_max_mb : 10;

  async function add(type: string, files: FileList | null) {
    if (!files) {
      return;
    }
    setFailures((f) => ({ ...f, [type]: "" }));
    for (const original of Array.from(files)) {
      const key = nextUploadKey();
      setUploads((u) => [...u, { key, type, name: original.name, percent: 0 }]);
      const prepared = (await prepareImage(original, SCAN_MAX_SIDE)) ?? original;
      if (prepared.size > maxMb * 1024 * 1024) {
        setFailures((f) => ({ ...f, [type]: t("tooLarge", { mb: maxMb }) }));
        setUploads((u) => u.filter((x) => x.key !== key));
        continue;
      }
      const form = new FormData();
      form.set("type", type);
      form.set("file", prepared, prepared.name);
      const result = await uploadWithProgress(`/api/seller/${unit.id}/documents?locale=${locale}`, form, (percent) =>
        setUploads((u) => u.map((x) => (x.key === key ? { ...x, percent } : x))),
      );
      setUploads((u) => u.filter((x) => x.key !== key));
      if (result.status !== 201) {
        const body = result.body as { message?: string } | null;
        setFailures((f) => ({ ...f, [type]: body?.message ?? t("failed") }));
      }
    }
    const fresh = await refreshUnitAction(unit.id, locale);
    if (fresh.ok && fresh.unit) {
      setUnit(fresh.unit);
    }
  }

  async function remove(document: SellerDocument) {
    const result = await deleteDocumentAction(unit.id, document.id, locale);
    if (result.ok && result.unit) {
      setUnit(result.unit);
    }
  }

  const missing = problemsOf(unit, 4);

  return (
    <ol className="flex flex-col gap-4">
      {SLOTS.map((slot, i) => {
        const files = unit.documents.filter((d) => d.type === slot.type);
        const cap = slot.setting === null ? 1 : Number(reference.settings[slot.setting] ?? 1);
        const required = slot.required || (slot.type === "receipt" && receiptsRequired);
        const pending = uploads.filter((u) => u.type === slot.type);
        // After submit a document can still be added (and an ID side replaced), never removed (core refuses).
        const canAdd = slot.setting === null ? unit.editable || files.length === 0 : files.length < cap;

        return (
          <Slot
            key={slot.type}
            number={String(i + 1).padStart(2, "0")}
            title={t(`types.${slot.type}.title`)}
            hint={t(`types.${slot.type}.hint`)}
            badge={required ? t("required") : t("optional")}
            files={files}
            pending={pending}
            canAdd={canAdd}
            replace={slot.setting === null && files.length > 0 && unit.editable}
            canRemove={unit.editable}
            error={failures[slot.type] || (missing[`documents.${slot.type}`] && errors[`documents.${slot.type}`]) || undefined}
            onAdd={(list) => void add(slot.type, list)}
            onRemove={(d) => void remove(d)}
            multiple={slot.setting !== null}
            unitId={unit.id}
          />
        );
      })}
    </ol>
  );
}

/** The documents panel: who sees them (board 07.2). */
export function DocumentsPrivacy() {
  const t = useTranslations("sell.step4");
  const common = useTranslations("sell.common");
  const { reference } = useWizard();
  const maxMb = typeof reference.settings.upload_max_mb === "number" ? reference.settings.upload_max_mb : 10;

  return (
    <div className="flex flex-col gap-4 rounded-sm border border-line bg-surface-1 p-5 text-small">
      <div className="flex items-center justify-between">
        <h2 className="text-body font-semibold">{t("whoTitle")}</h2>
        <span className="font-mono text-micro text-text-3" dir="ltr">
          PRIVATE
        </span>
      </div>
      <dl className="flex flex-col gap-2">
        <div className="flex justify-between">
          <dt className="text-text-2">{t("whoBuyers")}</dt>
          <dd>{common("no")}</dd>
        </div>
        <div className="flex justify-between">
          <dt className="text-text-2">{t("whoTeam")}</dt>
          <dd>{common("yes")}</dd>
        </div>
      </dl>
      <p className="text-text-3">{t("whoNote")}</p>
      <p className="font-mono text-micro text-text-3" dir="ltr">
        PDF · JPG · PNG · ≤ {maxMb} MB / file
      </p>
    </div>
  );
}

export function Step4Documents() {
  const t = useTranslations("sell.step4");
  const common = useTranslations("sell.common");
  const { unit } = useWizard();
  const { next, errors } = useStep<Record<string, never>>(4, {});
  const [uploads, setUploads] = useState<Upload[]>([]);
  const busy = uploads.length > 0;

  return (
    <WizardFrame step={4} title={t("title")} intro={t("intro")} aside={<DocumentsPrivacy />}>
      <DocumentSlots uploads={uploads} setUploads={setUploads} errors={errors} />
      <StepNav
        backHref="3"
        backLabel={common("back3")}
        nextLabel={t("next")}
        onNext={() => void next(`/sell/${unit.id}/5`)}
        disabled={busy}
        waiting={busy ? t("waiting", { name: uploads[0]?.name ?? "" }) : null}
      />
    </WizardFrame>
  );
}

function Slot({
  number,
  title,
  hint,
  badge,
  files,
  pending,
  canAdd,
  replace,
  canRemove,
  error,
  onAdd,
  onRemove,
  multiple,
  unitId,
}: {
  number: string;
  title: string;
  hint: string;
  badge: string;
  files: SellerDocument[];
  pending: Upload[];
  canAdd: boolean;
  replace: boolean;
  canRemove: boolean;
  error: string | undefined;
  onAdd: (files: FileList | null) => void;
  onRemove: (document: SellerDocument) => void;
  multiple: boolean;
  unitId: string;
}) {
  const t = useTranslations("sell.step4");
  const id = useId();

  return (
    <li className="flex flex-col gap-3 rounded-sm border border-line bg-surface-1 p-5">
      <div className="flex flex-wrap items-baseline gap-3">
        <span className="font-mono text-annotation text-text-3" dir="ltr">
          {number}
        </span>
        <h2 className="text-body font-semibold">{title}</h2>
        <span className="text-micro text-text-3">{badge}</span>
      </div>
      <p className="text-small text-text-3">{hint}</p>

      {files.length > 0 ? (
        <ul className="flex flex-col gap-2">
          {files.map((d) => (
            <li key={d.id} className="flex flex-wrap items-center gap-3 text-small">
              <span className="text-success" aria-hidden="true">
                ✓
              </span>
              <a href={`/api/seller/${unitId}/documents/${d.id}`} className="inline-flex min-h-control-md items-center font-mono break-all text-text-1 underline-offset-4 hover:underline" dir="ltr">
                {d.name} · {size(d.bytes)}
              </a>
              {d.status === "needs_replacement" ? <span className="text-warning">{t("needsReplacement")}</span> : null}
              {canRemove ? (
                <button type="button" onClick={() => onRemove(d)} className="ms-auto text-text-3 hover:text-error">
                  {t("remove")}
                </button>
              ) : null}
            </li>
          ))}
        </ul>
      ) : null}

      {pending.map((u) => (
        <div key={u.key} className="flex items-center gap-3 text-small">
          <span className="font-mono break-all" dir="ltr">
            {u.name}
          </span>
          <progress max={100} value={u.percent} className="h-1 flex-1 accent-[var(--color-text-1)]" aria-label={u.name} />
          <span className="font-mono tabular" dir="ltr">
            {u.percent}%
          </span>
        </div>
      ))}

      {canAdd || replace ? (
        <label
          htmlFor={id}
          className="flex min-h-11 cursor-pointer items-center justify-center rounded-sm border border-dashed border-line-strong px-4 py-4 text-small text-text-2 hover:border-text-1 hover:text-text-1"
          onDragOver={(e) => e.preventDefault()}
          onDrop={(e) => {
            e.preventDefault();
            onAdd(e.dataTransfer.files);
          }}
        >
          {replace ? t("replace") : t("drop")}
          <input
            id={id}
            type="file"
            accept="application/pdf,image/jpeg,image/png,image/heic,image/heif,.heic,.heif"
            multiple={multiple}
            className="sr-only"
            onChange={(e) => {
              onAdd(e.target.files);
              e.target.value = "";
            }}
          />
        </label>
      ) : null}
      {error ? (
        <p role="alert" className="text-small text-error">
          {error}
        </p>
      ) : null}
    </li>
  );
}
