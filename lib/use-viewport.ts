"use client";

import { useEffect, useState } from "react";

/**
 * Lebar viewport, `null` sebelum komponen ter-mount (server tidak tahu lebar
 * layar). Dipakai untuk menghitung ulang path notch tab bar tiap resize.
 */
export function useViewportWidth(): number | null {
  const [width, setWidth] = useState<number | null>(null);

  useEffect(() => {
    const onResize = () => setWidth(window.innerWidth);
    onResize();
    window.addEventListener("resize", onResize);
    return () => window.removeEventListener("resize", onResize);
  }, []);

  return width;
}

/**
 * Path bar bawah dengan cekungan setengah lingkaran di tengah:
 * garis atas datar → fillet 14px → setengah lingkaran r=36 mencekung ke bawah
 * → fillet keluar → sisi kanan.
 */
export function tabBarPath(w: number, h = 78, r = 36, f = 14): string {
  const cx = w / 2;
  return `M0,0 H${cx - r - f} q${f},0 ${f},${f} a${r},${r} 0 0 0 ${2 * r},0 q0,${-f} ${f},${-f} H${w} V${h} H0 Z`;
}
