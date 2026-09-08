import type { Config } from "tailwindcss";

const config: Config = {
  content: ["./src/**/*.{js,ts,jsx,tsx,mdx}"],
  theme: {
    extend: {
      colors: {
        brand: {
          50: "#f0f7ff",
          100: "#dceeff",
          200: "#b6dcff",
          300: "#7fc2ff",
          400: "#42a1ff",
          500: "#1682f5",
          600: "#0964d1",
          700: "#0a4fa8",
          800: "#0e4288",
          900: "#113871",
        },
      },
    },
  },
  plugins: [],
};
export default config;
