import type { Metadata } from "next";
import Link from "next/link";

export const metadata: Metadata = { robots: { index: false, follow: false } };

/**
 * A path outside both languages (an unknown language prefix reaches here): a plain bilingual page with its own
 * <html>, since the root layout leaves the document to the language layout (audit D6).
 */
export default function RootNotFound() {
  return (
    <html lang="ar" dir="rtl">
      <body style={{ margin: 0, background: "#090C11", color: "#EDF1F6" }}>
        <main style={{ maxWidth: 560, margin: "15vh auto", padding: "0 20px", fontFamily: "system-ui, sans-serif", lineHeight: 1.7 }}>
          <h1 style={{ fontSize: 28, margin: "0 0 8px" }}>الصفحة دي مش موجودة</h1>
          <p lang="en" dir="ltr" style={{ color: "#AEB9C8", margin: 0 }}>
            This page does not exist.
          </p>
          <p style={{ marginTop: 16 }}>
            <Link href="/" style={{ color: "#EDF1F6" }}>
              الرئيسية
            </Link>{" "}
            ·{" "}
            <Link href="/en" lang="en" style={{ color: "#EDF1F6" }}>
              Home
            </Link>
          </p>
        </main>
      </body>
    </html>
  );
}
