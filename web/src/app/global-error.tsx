"use client";

/**
 * The last fallback, when even the language layout failed (audit D6). It replaces the whole document, so it carries
 * its own <html>, plain styles and both languages: no font files, no messages, nothing that could fail again.
 */
export default function GlobalError({ reset }: { error: Error & { digest?: string }; reset: () => void }) {
  const box = { maxWidth: 560, margin: "15vh auto", padding: "0 20px", fontFamily: "system-ui, sans-serif", lineHeight: 1.7 } as const;
  const button = { marginTop: 16, minHeight: 44, padding: "0 20px", border: 0, borderRadius: 2, background: "#D6AC55", color: "#0D1118", fontWeight: 600, cursor: "pointer" } as const;

  return (
    <html lang="ar" dir="rtl">
      <body style={{ margin: 0, background: "#090C11", color: "#EDF1F6" }}>
        <main style={box}>
          <h1 style={{ fontSize: 28, margin: "0 0 8px" }}>حصلت مشكلة عندنا</h1>
          <p style={{ color: "#AEB9C8", margin: 0 }}>الصفحة ما اتحمّلتش. جرّب تاني بعد شوية.</p>
          <p lang="en" dir="ltr" style={{ color: "#AEB9C8", margin: "16px 0 0" }}>
            Something went wrong on our side. Try again in a moment.
          </p>
          <button type="button" onClick={reset} style={button}>
            جرّب تاني · Try again
          </button>
        </main>
      </body>
    </html>
  );
}
