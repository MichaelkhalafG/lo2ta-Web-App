/*
 * Browser-side preparation before an upload (PLAN 2.2.14, 2.2.15; Michael 2026-11-18):
 * - HEIC/HEIF (the iPhone default) becomes JPEG in the browser; the decoder (heic-to, LGPL-3.0, its own chunk) loads
 *   only when such a file is picked;
 * - photos shrink to 2560 px on the long side, scans to 4000 px, so uploads are light and the server's 25 MP cap is
 *   never reached by an honest file. Orientation from the phone is applied by the browser while drawing.
 * The server still checks everything; this only spares the seller refusals and mobile data.
 * PDFs and anything that is not an image pass through unchanged.
 */

export const PHOTO_MAX_SIDE = 2560;
export const SCAN_MAX_SIDE = 4000;

async function looksHeic(file: File): Promise<boolean> {
  if (/image\/hei[cf]/i.test(file.type) || /\.(heic|heif)$/i.test(file.name)) {
    return true;
  }
  const head = new Uint8Array(await file.slice(0, 12).arrayBuffer());
  const brand = String.fromCharCode(...head.slice(4, 12));

  return brand.startsWith("ftyp") && /heic|heix|hevc|mif1|msf1/.test(brand.slice(4));
}

async function decode(blob: Blob): Promise<ImageBitmap | null> {
  try {
    return await createImageBitmap(blob, { imageOrientation: "from-image" });
  } catch {
    return null;
  }
}

/**
 * The file to upload: converted and shrunk when it is an image, as is otherwise.
 * Returns null when an image cannot be read at all (the caller shows a message).
 */
export async function prepareImage(file: File, maxSide: number): Promise<File | null> {
  const heic = await looksHeic(file);
  if (!heic && !/^image\/(jpeg|png|webp)$/.test(file.type)) {
    return file;
  }

  let bitmap = heic ? null : await decode(file);
  if (heic) {
    // Safari reads HEIC itself; elsewhere the decoder is loaded now, never before.
    bitmap = await decode(file);
    if (!bitmap) {
      const { heicTo } = await import("heic-to/next");
      bitmap = await heicTo({ blob: file, type: "bitmap" }).catch(() => null);
    }
  }
  if (!bitmap) {
    return heic ? null : file;
  }

  const scale = Math.min(1, maxSide / Math.max(bitmap.width, bitmap.height));
  if (scale === 1 && !heic && file.type !== "image/png") {
    bitmap.close();
    return file;
  }
  const width = Math.max(1, Math.round(bitmap.width * scale));
  const height = Math.max(1, Math.round(bitmap.height * scale));
  const canvas = document.createElement("canvas");
  canvas.width = width;
  canvas.height = height;
  const context = canvas.getContext("2d");
  if (!context) {
    bitmap.close();
    return file;
  }
  context.drawImage(bitmap, 0, 0, width, height);
  bitmap.close();

  const keepPng = file.type === "image/png" && !heic;
  const blob = await new Promise<Blob | null>((resolve) => canvas.toBlob(resolve, keepPng ? "image/png" : "image/jpeg", 0.9));
  if (!blob) {
    return file;
  }
  const base = file.name.replace(/\.[^.]+$/, "") || "image";

  return new File([blob], `${base}.${keepPng ? "png" : "jpg"}`, { type: blob.type });
}

/**
 * Upload with progress (fetch has no upload progress). Resolves with the status and the JSON body.
 */
export function uploadWithProgress(url: string, form: FormData, onProgress: (percent: number) => void): Promise<{ status: number; body: unknown }> {
  return new Promise((resolve) => {
    const request = new XMLHttpRequest();
    request.open("POST", url);
    request.responseType = "json";
    request.upload.onprogress = (event) => {
      if (event.lengthComputable) {
        onProgress(Math.min(99, Math.round((event.loaded / event.total) * 100)));
      }
    };
    request.onload = () => {
      onProgress(100);
      resolve({ status: request.status, body: request.response as unknown });
    };
    request.onerror = () => resolve({ status: 0, body: null });
    request.send(form);
  });
}
