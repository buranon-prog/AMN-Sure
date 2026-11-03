import type { Config } from "tailwindcss";

const config: Config = {
  content: ["./src/**/*.{js,ts,jsx,tsx,mdx}"],
  theme: {
    extend: {
      colors: {
        brand: {
          50: "#eefbf6",
          100: "#d5f5e8",
          200: "#aeead4",
          300: "#77d9ba",
          400: "#3fc19b",
          500: "#1ca682",
          600: "#10866a",
          700: "#0f6b57",
          800: "#105547",
          900: "#0f463b",
        },
      },
    },
  },
  plugins: [],
};
export default config;
