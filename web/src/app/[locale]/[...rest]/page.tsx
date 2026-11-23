import type { Metadata } from "next";
import { notFound } from "next/navigation";

export const metadata: Metadata = { robots: { index: false, follow: false } };

/** A path no page matches, under a language: the site's own not-found page, in that language (audit D6). */
export default function UnknownPath() {
  notFound();
}
