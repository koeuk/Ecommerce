import defaultTheme from "tailwindcss/defaultTheme";
import forms from "@tailwindcss/forms";

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        "./vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php",
        "./storage/framework/views/*.php",
        "./resources/views/**/*.blade.php",
        "./resources/js/**/*.vue",
        "./node_modules/flowbite/**/*.js", // // add require("flowbite/plugins") affter instal npm install flowbite
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ["Figtree", ...defaultTheme.fontFamily.sans],
            },
            // Deep green brand palette — use brand-700/800 for primary actions
            colors: {
                brand: {
                    50: "#f0fdf4",
                    100: "#dcfce7",
                    200: "#bbf7d0",
                    300: "#86efac",
                    400: "#4ade80",
                    500: "#22c55e",
                    600: "#16a34a",
                    700: "#15803d",
                    800: "#125e34",
                    900: "#0d4726",
                    950: "#052e16",
                },
            },
        },
    },

    plugins: [forms, require("flowbite/plugin")],  // add require("flowbite/plugins") affter instal npm install flowbite
};
