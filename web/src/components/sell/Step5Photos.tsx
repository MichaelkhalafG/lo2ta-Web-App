"use client";

import { useTranslations } from "next-intl";
import { useId, useState } from "react";
import { deletePhotoAction, refreshUnitAction, reorderPhotosAction } from "@/lib/actions/sell";
import { PHOTO_MAX_SIDE, prepareImage, uploadWithProgress } from "@/lib/imagePrep";
import type { SellerPhoto } from "@/lib/sell";
import { TextArea } from "./fields";
import { StepNav, WizardFrame } from "./WizardFrame";
import { useStep, useWizard } from "./WizardContext";

/*
 * Step 5, photos and description (PLAN 2.2.2, 2.2.9 point 7, 2.2.14). Photos shrink in the browser to 2560 px and
 * HEIC becomes JPEG first; the first photo is the cover; "Project image" marks renders and project photos; the order
 * changes by dragging or with the move buttons. The description refuses phone numbers, e-mails and links (core).
 */

/** Keys for the upload rows: unique within the page, nothing more. */
let uploadSeq = 0;
function nextUploadKey(): string {
  uploadSeq += 1;

  return `upload-${uploadSeq}`;
}

export type PendingPhoto = { key: string; name: string; percent: number };

/** Photos and floor plan with upload, order, cover, "Project image" and removal. Step 5 and the unit page share it. */
export function PhotoManager({
  pending,
  setPending,
  error,
}: {
  pending: PendingPhoto[];
  setPending: (update: (p: PendingPhoto[]) => PendingPhoto[]) => void;
  error?: string | undefined;
}) {
  const t = useTranslations("sell.step5");
  const { unit, setUnit, reference, locale } = useWizard();
  const [failure, setFailure] = useState<string | null>(null);
  const [dragging, setDragging] = useState<string | null>(null);
  const inputId = useId();
  const planId = useId();

  const photos = unit.photos.filter((p) => p.kind === "photo");
  const plan = unit.photos.find((p) => p.kind === "floor_plan") ?? null;
  const maxPhotos = Number(reference.settings.photo_max_count ?? 10);
  const maxMb = Number(reference.settings.upload_max_mb ?? 10);

  async function upload(files: FileList | null, kind: "photo" | "floor_plan") {
    if (!files) {
      return;
    }
    setFailure(null);
    for (const original of Array.from(files).slice(0, kind === "photo" ? maxPhotos - photos.length : 1)) {
      const key = nextUploadKey();
      setPending((p) => [...p, { key, name: original.name, percent: 0 }]);
      const prepared = await prepareImage(original, PHOTO_MAX_SIDE);
      if (!prepared || prepared.size > maxMb * 1024 * 1024) {
        setFailure(prepared ? t("tooLarge", { mb: maxMb }) : t("unreadable"));
        setPending((p) => p.filter((x) => x.key !== key));
        continue;
      }
      const form = new FormData();
      form.set("kind", kind);
      form.set("is_project_image", "0");
      form.set("file", prepared, prepared.name);
      const result = await uploadWithProgress(`/api/seller/${unit.id}/photos?locale=${locale}`, form, (percent) =>
        setPending((p) => p.map((x) => (x.key === key ? { ...x, percent } : x))),
      );
      setPending((p) => p.filter((x) => x.key !== key));
      if (result.status !== 201) {
        setFailure((result.body as { message?: string } | null)?.message ?? t("failed"));
      }
    }
    const fresh = await refreshUnitAction(unit.id, locale);
    if (fresh.ok && fresh.unit) {
      setUnit(fresh.unit);
    }
  }

  async function save(order: SellerPhoto[], projectImages: string[]) {
    const result = await reorderPhotosAction(unit.id, order.map((p) => p.id), projectImages, locale);
    if (result.ok && result.unit) {
      setUnit(result.unit);
    }
  }

  function move(id: string, to: number) {
    const order = photos.filter((p) => p.id !== id);
    const photo = photos.find((p) => p.id === id);
    if (!photo) {
      return;
    }
    order.splice(Math.max(0, Math.min(order.length, to)), 0, photo);
    void save(order, photos.filter((p) => p.is_project_image).map((p) => p.id));
  }

  async function remove(photo: SellerPhoto) {
    const result = await deletePhotoAction(unit.id, photo.id, locale);
    if (result.ok && result.unit) {
      setUnit(result.unit);
    }
  }

  return (
    <>
      <section className="flex flex-col gap-4">
        <div className="flex items-baseline justify-between">
          <h2 className="text-h3">{t("photos")}</h2>
          <span className="font-mono text-annotation text-text-3 tabular" dir="ltr">
            {photos.length} / {maxPhotos}
          </span>
        </div>
        <ol className="grid grid-cols-2 gap-3 md:grid-cols-4">
          {photos.map((p, i) => (
            <li
              key={p.id}
              draggable
              onDragStart={() => setDragging(p.id)}
              onDragOver={(e) => e.preventDefault()}
              onDrop={() => {
                if (dragging && dragging !== p.id) {
                  move(dragging, i);
                }
                setDragging(null);
              }}
              className="flex flex-col gap-2 rounded-sm border border-line bg-surface-1 p-2"
            >
              {/* eslint-disable-next-line @next/next/no-img-element -- a private, signed-in image; next/image would cache it */}
              <img src={`/api/seller/${unit.id}/photos/${p.id}`} alt={t("photoAlt", { n: i + 1 })} className="aspect-[4/3] w-full rounded-sm object-cover" />
              <div className="flex flex-wrap items-center gap-2 text-micro">
                {p.is_cover ? <span className="text-gold">{t("cover")}</span> : null}
                {p.is_hidden ? <span className="text-warning">{t("hidden")}</span> : null}
                <label className="flex items-center gap-1 text-text-2">
                  <input
                    type="checkbox"
                    checked={p.is_project_image}
                    onChange={(e) => void save(photos, photos.filter((x) => (x.id === p.id ? e.target.checked : x.is_project_image)).map((x) => x.id))}
                    className="size-3.5 accent-[var(--color-text-1)]"
                  />
                  {t("projectImage")}
                </label>
              </div>
              <div className="flex items-center gap-1 text-small">
                <button type="button" disabled={i === 0} onClick={() => move(p.id, i - 1)} className="px-2 text-text-2 disabled:opacity-30" aria-label={t("moveEarlier")}>
                  <span aria-hidden="true" className="inline-block rtl:rotate-180">
                    ←
                  </span>
                </button>
                <button type="button" disabled={i === photos.length - 1} onClick={() => move(p.id, i + 1)} className="px-2 text-text-2 disabled:opacity-30" aria-label={t("moveLater")}>
                  <span aria-hidden="true" className="inline-block rtl:rotate-180">
                    →
                  </span>
                </button>
                <button type="button" onClick={() => void remove(p)} className="ms-auto text-text-3 hover:text-error">
                  {t("remove")}
                </button>
              </div>
            </li>
          ))}
          {pending.map((u) => (
            <li key={u.key} className="flex aspect-[4/3] flex-col items-center justify-center gap-2 rounded-sm border border-line bg-surface-1 p-3 text-small">
              <progress max={100} value={u.percent} className="h-1 w-full" aria-label={u.name} />
              <span className="font-mono tabular" dir="ltr">
                {u.percent}%
              </span>
            </li>
          ))}
        </ol>
        {photos.length + pending.length < maxPhotos ? (
          <label htmlFor={inputId} className="flex min-h-11 cursor-pointer items-center justify-center rounded-sm border border-dashed border-line-strong px-4 py-5 text-small text-text-2 hover:border-text-1 hover:text-text-1">
            {t("add")}
            <input
              id={inputId}
              type="file"
              multiple
              accept="image/jpeg,image/png,image/webp,image/heic,image/heif,.heic,.heif"
              className="sr-only"
              onChange={(e) => {
                void upload(e.target.files, "photo");
                e.target.value = "";
              }}
            />
          </label>
        ) : null}
        {error ? <p className="text-small text-error">{error}</p> : null}
        {failure ? (
          <p role="alert" className="text-small text-error">
            {failure}
          </p>
        ) : null}
      </section>

      <section className="flex flex-col gap-3">
        <h2 className="text-h3">{t("floorPlan")}</h2>
        {plan ? (
          <div className="flex items-center gap-3">
            {/* eslint-disable-next-line @next/next/no-img-element -- private image, see above */}
            <img src={`/api/seller/${unit.id}/photos/${plan.id}`} alt={t("floorPlan")} className="h-24 w-32 rounded-sm object-contain" />
            <button type="button" onClick={() => void remove(plan)} className="text-small text-text-3 hover:text-error">
              {t("remove")}
            </button>
          </div>
        ) : (
          <label htmlFor={planId} className="flex min-h-11 cursor-pointer items-center justify-center rounded-sm border border-dashed border-line-strong px-4 py-4 text-small text-text-2 hover:text-text-1">
            {t("addPlan")}
            <input
              id={planId}
              type="file"
              accept="image/jpeg,image/png,image/webp,image/heic,image/heif,.heic,.heif"
              className="sr-only"
              onChange={(e) => {
                void upload(e.target.files, "floor_plan");
                e.target.value = "";
              }}
            />
          </label>
        )}
      </section>
    </>
  );
}

export function Step5Photos() {
  const t = useTranslations("sell.step5");
  const common = useTranslations("sell.common");
  const { unit, reference } = useWizard();
  const { values, set, errors, next } = useStep<{ description: string | null }>(5, unit.steps.step5);
  const [pending, setPending] = useState<PendingPhoto[]>([]);
  const minChars = Number(reference.settings.description_min ?? 60);
  const maxChars = Number(reference.settings.description_max ?? 1200);
  const description = values.description ?? "";

  return (
    <WizardFrame step={5} title={t("title")} intro={t("intro")}>
      <p className="rounded-sm border border-warning/40 bg-warning-tint px-4 py-3 text-small text-warning">{reference.texts["seller.photos.warning"] ?? ""}</p>

      <PhotoManager pending={pending} setPending={setPending} error={errors.photos} />

      <TextArea
        label={t("description")}
        required
        max={maxChars}
        value={values.description}
        onChange={(v) => set("description", v)}
        hint={t("descriptionHint", { min: minChars, max: maxChars })}
        error={errors.description}
        counter={`${description.length} / ${maxChars}`}
      />

      <StepNav backHref="4" backLabel={common("back4")} nextLabel={t("next")} onNext={() => void next(`/sell/${unit.id}/6`)} disabled={pending.length > 0} />
    </WizardFrame>
  );
}
