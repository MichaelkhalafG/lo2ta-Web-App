/*
 * The unit gallery's arithmetic (proposal approved 2026-11-24): which pictures stand on the sheet and which wait in
 * the viewer. Pure, so it is tested without a browser.
 *
 * Wide screens: the first photo large; beside it the floor plan (the most useful sheet after the photos) and the next
 * photo, which carries "+N" for the pictures only the viewer shows. Phones: every picture in one swipe strip.
 * The viewer holds the photos in order, then the floor plan.
 */

export type Picture = { src: string; width: number; height: number; is_project_image: boolean; kind: "photo" | "floor_plan" };

export type GalleryLayout = {
  /** Every picture in viewer order: photos first, the floor plan last. */
  items: Picture[];
  main: number | null;
  /** Up to two side tiles (indexes into items); the last one may carry "+more". */
  side: number[];
  /** Pictures not on the sheet, shown as "+N" on the last side tile (0 = no badge). */
  more: number;
};

export function galleryLayout(photos: Picture[], plan: Picture | null): GalleryLayout {
  const items = [...photos, ...(plan ? [plan] : [])];
  if (items.length === 0) {
    return { items, main: null, side: [], more: 0 };
  }
  const planIndex = plan ? items.length - 1 : null;
  const side: number[] = [];
  if (planIndex !== null && planIndex !== 0) side.push(planIndex);
  for (let i = 1; i < photos.length && side.length < 2; i++) side.push(i);
  // With a plan, the plan comes first beside the main photo; without one, two photos stand there.
  const shown = 1 + side.length;

  return { items, main: 0, side, more: Math.max(0, items.length - shown) };
}

/** The next index in the viewer, wrapping at both ends. */
export function step(index: number, delta: number, length: number): number {
  if (length <= 0) return 0;
  return (((index + delta) % length) + length) % length;
}

/**
 * Arrow keys follow the reading direction: in Arabic the next picture is to the left. Returns the step (-1, 0, 1).
 */
export function arrowStep(key: string, dir: "rtl" | "ltr"): -1 | 0 | 1 {
  if (key !== "ArrowLeft" && key !== "ArrowRight") return 0;
  const forward = dir === "rtl" ? "ArrowLeft" : "ArrowRight";
  return key === forward ? 1 : -1;
}
