import { alpha, createTheme } from "@mui/material/styles";
import "@fontsource/cormorant-garamond/500.css";
import "@fontsource/cormorant-garamond/600.css";
import "@fontsource/cormorant-garamond/700.css";
import "@fontsource/inter/400.css";
import "@fontsource/inter/500.css";
import "@fontsource/inter/600.css";
import "@fontsource/inter/700.css";

export const colores = {
  vino: "#8F1D24",
  vinoOscuro: "#661D24",
  vinoProfundo: "#4B1217",
  vinoSuave: "#EADBDC",
  vinoTenue: "#F6EEEF",
  rosaCeniza: "#D7ADB1",
  lienzo: "#F4F3F5",
  papel: "#FFFFFF",
  tinta: "#24181A",
  piedra: "#6B5F63",
  linea: "#E6E0E2",
  // Estados (siempre acompañados de ícono y texto)
  ok: "#2F6B4F",
  alerta: "#9A5B00",
  critico: "#B42318",
  info: "#1F6F8B",
  // Series de gráficos (validadas para daltonismo y contraste)
  serie1: "#B8343C",
  serie2: "#1F87A8",
} as const;

export const fuenteTitulos = '"Cormorant Garamond", "Times New Roman", serif';
export const fuenteTexto =
  '"Inter", system-ui, -apple-system, "Segoe UI", Roboto, sans-serif';

export const tema = createTheme({
  palette: {
    mode: "light",
    primary: {
      main: colores.vino,
      dark: colores.vinoOscuro,
      light: "#B0464C",
      contrastText: "#fff",
    },
    secondary: { main: colores.info, contrastText: "#fff" },
    error: { main: colores.critico },
    warning: { main: colores.alerta },
    success: { main: colores.ok },
    info: { main: colores.info },
    background: { default: colores.lienzo, paper: colores.papel },
    text: { primary: colores.tinta, secondary: colores.piedra },
    divider: colores.linea,
  },
  shape: { borderRadius: 8 },
  typography: {
    fontFamily: fuenteTexto,
    fontSize: 14,
    h1: {
      fontFamily: fuenteTitulos,
      fontWeight: 600,
      fontSize: "2.25rem",
      lineHeight: 1.1,
      letterSpacing: "-0.01em",
    },
    h2: {
      fontFamily: fuenteTitulos,
      fontWeight: 600,
      fontSize: "1.6rem",
      lineHeight: 1.15,
    },
    h3: {
      fontFamily: fuenteTitulos,
      fontWeight: 600,
      fontSize: "1.3rem",
      lineHeight: 1.2,
    },
    h4: { fontFamily: fuenteTexto, fontWeight: 600, fontSize: "1rem" },
    h5: { fontFamily: fuenteTexto, fontWeight: 600, fontSize: "0.95rem" },
    h6: { fontFamily: fuenteTexto, fontWeight: 600, fontSize: "0.875rem" },
    subtitle1: { color: colores.piedra, lineHeight: 1.5 },
    body2: { lineHeight: 1.55 },
    button: { textTransform: "none", fontWeight: 600, letterSpacing: 0 },
    overline: {
      textTransform: "none",
      letterSpacing: 0,
      fontWeight: 600,
      fontSize: "0.75rem",
    },
  },
  components: {
    MuiCssBaseline: {
      styleOverrides: {
        body: { fontVariantNumeric: "tabular-nums" },
        "::selection": { background: colores.vinoSuave },
        ":focus-visible": {
          outline: `2px solid ${colores.vino}`,
          outlineOffset: 2,
        },
        "@media (prefers-reduced-motion: reduce)": {
          "*, *::before, *::after": {
            animationDuration: "0.01ms !important",
            transitionDuration: "0.01ms !important",
          },
        },
      },
    },
    MuiButton: {
      defaultProps: { disableElevation: true },
      styleOverrides: {
        root: { borderRadius: 8, paddingInline: 16 },
        sizeSmall: { paddingInline: 10 },
      },
    },
    MuiPaper: {
      defaultProps: { elevation: 0 },
      styleOverrides: {
        root: { backgroundImage: "none" },
        outlined: { borderColor: colores.linea },
      },
    },
    MuiCard: { defaultProps: { variant: "outlined" } },
    MuiTextField: { defaultProps: { size: "small", fullWidth: true } },
    MuiSelect: { defaultProps: { size: "small" } },
    MuiOutlinedInput: {
      styleOverrides: {
        root: { backgroundColor: colores.papel },
        notchedOutline: { borderColor: "#D9D1D4" },
      },
    },
    MuiChip: {
      styleOverrides: { root: { fontWeight: 500 }, sizeSmall: { height: 24 } },
    },
    MuiTableCell: {
      styleOverrides: {
        head: {
          fontWeight: 600,
          color: colores.piedra,
          backgroundColor: "#FAF9FA",
          fontSize: "0.8rem",
        },
        root: { borderColor: colores.linea },
      },
    },
    MuiTableRow: {
      styleOverrides: {
        hover: {
          "&:hover": {
            backgroundColor: `${alpha(colores.vino, 0.035)} !important`,
          },
        },
      },
    },
    MuiDialogTitle: {
      styleOverrides: {
        root: {
          fontFamily: fuenteTitulos,
          fontWeight: 600,
          fontSize: "1.5rem",
        },
      },
    },
    MuiTooltip: {
      styleOverrides: {
        tooltip: { backgroundColor: colores.tinta, fontSize: "0.78rem" },
      },
    },
    MuiTab: {
      styleOverrides: {
        root: { textTransform: "none", fontWeight: 600, minHeight: 44 },
      },
    },
    MuiAlert: { styleOverrides: { root: { borderRadius: 8 } } },
  },
});
