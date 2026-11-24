"use client";

import { useEffect, useRef, useState, type ReactNode } from "react";
import { arrowStep, galleryLayout, step, type Picture } from "@/lib/gallery";

export type GalleryWords = {
  photos: string;
  photoN: string;
  ownerPhoto: string;
  projectImage: string;
  floorPlan: string;
  floorPlanBy: string;
  enlarge: string;
  more: string;
  openPlan: string;
  close: string;
  next: string;
  previous: string;
  viewer: string;
};

/*
 * The unit gallery (proposal approved 2026-11-24, board 06.3 and 06.4). Wide screens: the first photo large, the
 * floor plan and the next photo beside it, "+N" opening a full-screen viewer. Phones: one swipe strip with the count
 * and Save over it. The viewer is a native modal <dialog>: Escape closes it and focus returns to the picture that
 * opened it; arrow keys follow the reading direction. Without JavaScript every picture is still a plain link to
 * its file.
 */
export function UnitGallery({ photos, plan, title, dir, words, save }: { photos: Picture[]; plan: Picture | null; title: string; dir: "rtl" | "ltr"; words: GalleryWords; save: ReactNode }) {
  const layout = galleryLayout(photos, plan);
  const { items } = layout;
  const dialog = useRef<HTMLDialogElement>(null);
  const strip = useRef<HTMLDivElement>(null);
  const [open, setOpen] = useState(0);
  const [seen, setSeen] = useState(0);

  const alt = (i: number) => (items[i]?.kind === "floor_plan" ? words.floorPlan : i === 0 ? title : words.photoN.replace("{n}", String(i + 1)));
  const count = (i: number) => `${i + 1} / ${items.length}`;

  const show = (i: number) => {
    setOpen(i);
    dialog.current?.showModal();
  };

  // The phone strip's count follows the picture in view.
  useEffect(() => {
    const el = strip.current;
    if (!el) return;
    const onScroll = () => setSeen(Math.min(items.length - 1, Math.round(Math.abs(el.scrollLeft) / Math.max(1, el.clientWidth))));
    el.addEventListener("scroll", onScroll, { passive: true });
    return () => el.removeEventListener("scroll", onScroll);
  }, [items.length]);

  if (layout.main === null) {
    return null;
  }

  const tile = (i: number, className: string, overlay?: ReactNode) => {
    const p = items[i]!;
    return (
      <a
        href={p.src}
        onClick={(e) => {
          e.preventDefault();
          show(i);
        }}
        className={`group relative block overflow-hidden rounded-sm ${p.kind === "floor_plan" ? "border border-line bg-bg-1" : "bg-surface-1"} ${className}`}
      >
        {/* eslint-disable-next-line @next/next/no-img-element -- fixed-size WebP from core */}
        <img
          src={p.src}
          alt={alt(i)}
          width={p.width}
          height={p.height}
          loading={i === 0 ? undefined : "lazy"}
          fetchPriority={i === 0 ? "high" : undefined}
          className={`size-full ${p.kind === "floor_plan" ? "object-contain p-4" : "object-cover"}`}
        />
        {overlay}
      </a>
    );
  };

  const chip = "rounded-[2px] bg-bg-0/85 px-2.5 py-0.5 text-small text-text-2";
  const main = items[0]!;

  return (
    <section aria-label={words.photos} className="flex min-w-0 flex-col gap-3">
      {/* Wide screens */}
      <div className={`hidden gap-3 md:grid ${layout.side.length > 0 ? "md:h-[32rem] md:grid-cols-[minmax(0,2fr)_minmax(0,1fr)] md:grid-rows-2" : "md:aspect-[4/3]"}`}>
        {tile(
          0,
          layout.side.length > 0 ? "row-span-2" : "",
          <span className="absolute inset-x-4 bottom-4 flex justify-between gap-3">
            <span className={chip}>{main.kind === "floor_plan" ? words.floorPlan : main.is_project_image ? words.projectImage : words.ownerPhoto}</span>
            <span className={`${chip} font-mono text-annotation`} dir="ltr">
              {count(0)}
            </span>
          </span>,
        )}
        {layout.side.map((i, n) => {
          const p = items[i]!;
          const last = n === layout.side.length - 1;
          return tile(
            i,
            "",
            p.kind === "floor_plan" ? (
              <span className="absolute inset-x-4 bottom-4 flex justify-between gap-3">
                <span className={chip}>{words.floorPlanBy}</span>
                <span className={chip}>
                  {words.enlarge} <span aria-hidden="true">⤢</span>
                </span>
              </span>
            ) : last && layout.more > 0 ? (
              <span className="absolute inset-0 grid place-items-center bg-bg-0/40">
                <span className="rounded-[2px] bg-bg-0/90 px-3 py-1 text-body font-semibold text-text-1">{words.more.replace("{count}", String(layout.more))}</span>
              </span>
            ) : p.is_project_image ? (
              <span className={`absolute end-3 bottom-3 ${chip}`}>{words.projectImage}</span>
            ) : null,
          );
        })}
      </div>

      {/* Phones: one swipe strip, the count and Save over it (board 06.4). */}
      <div className="relative -mx-gutter-mobile md:hidden">
        <div ref={strip} className="flex snap-x snap-mandatory overflow-x-auto [scrollbar-width:none]" aria-label={words.photos} tabIndex={0}>
          {items.map((p, i) => (
            <a
              key={p.src}
              href={p.src}
              onClick={(e) => {
                e.preventDefault();
                show(i);
              }}
              className={`block aspect-[4/3] w-full shrink-0 snap-start ${p.kind === "floor_plan" ? "bg-bg-1" : "bg-surface-1"}`}
            >
              {/* eslint-disable-next-line @next/next/no-img-element -- fixed-size WebP from core */}
              <img src={p.src} alt={alt(i)} width={p.width} height={p.height} loading={i === 0 ? undefined : "lazy"} className={`size-full ${p.kind === "floor_plan" ? "object-contain p-4" : "object-cover"}`} />
            </a>
          ))}
        </div>
        <div className="pointer-events-none absolute inset-x-3 top-3 flex items-start justify-between">
          <span className="pointer-events-auto">{save}</span>
          <span className="min-h-control-md content-center rounded-[2px] bg-bg-0/90 px-3 font-mono text-annotation text-text-1" dir="ltr" aria-live="polite">
            {count(seen)}
          </span>
        </div>
      </div>
      {plan ? (
        <div className="flex items-center justify-between border-b border-line text-small md:hidden">
          <span className="text-text-2">{words.floorPlan}</span>
          <button type="button" onClick={() => show(items.length - 1)} className="inline-flex min-h-control-md items-center gap-1 text-text-1 underline underline-offset-4">
            {words.openPlan} <span aria-hidden="true">⤢</span>
          </button>
        </div>
      ) : null}

      <dialog
        ref={dialog}
        aria-label={words.viewer}
        onKeyDown={(e) => {
          const d = arrowStep(e.key, dir);
          if (d !== 0) {
            e.preventDefault();
            setOpen((i) => step(i, d, items.length));
          }
        }}
        className="m-0 h-dvh max-h-none w-screen max-w-none bg-bg-0/97 p-0 text-text-1 backdrop:bg-bg-0/80"
      >
        <div className="grid h-full grid-rows-[auto_minmax(0,1fr)_auto] gap-3 p-4 md:p-8">
          <div className="flex items-center justify-between">
            <span className="font-mono text-annotation text-text-2" dir="ltr">
              {count(open)}
            </span>
            <button type="button" onClick={() => dialog.current?.close()} aria-label={words.close} className="grid size-12 place-items-center rounded-sm border border-line-strong hover:bg-surface-2">
              <span aria-hidden="true">✕</span>
            </button>
          </div>
          {items[open] ? (
            /* eslint-disable-next-line @next/next/no-img-element -- fixed-size WebP from core */
            <img src={items[open]!.src} alt={alt(open)} width={items[open]!.width} height={items[open]!.height} className="m-auto max-h-full max-w-full object-contain" />
          ) : null}
          {items.length > 1 ? (
            <div className="flex items-center justify-center gap-4">
              <button type="button" onClick={() => setOpen((i) => step(i, -1, items.length))} aria-label={words.previous} className="grid size-12 place-items-center rounded-sm border border-line-strong hover:bg-surface-2">
                <span aria-hidden="true" className="rtl:rotate-180">
                  ←
                </span>
              </button>
              <span className="min-w-40 text-center text-small text-text-2">{items[open]?.kind === "floor_plan" ? words.floorPlan : items[open]?.is_project_image ? words.projectImage : words.ownerPhoto}</span>
              <button type="button" onClick={() => setOpen((i) => step(i, 1, items.length))} aria-label={words.next} className="grid size-12 place-items-center rounded-sm border border-line-strong hover:bg-surface-2">
                <span aria-hidden="true" className="rtl:rotate-180">
                  →
                </span>
              </button>
            </div>
          ) : null}
        </div>
      </dialog>
    </section>
  );
}
