import { zodResolver } from "@hookform/resolvers/zod";
import {
  Alert,
  Box,
  Button,
  Checkbox,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  FormControlLabel,
  InputAdornment,
  MenuItem,
  Switch,
  TextField,
} from "@mui/material";
import { useEffect, useState, type ReactNode } from "react";
import { Controller, useForm, type FieldValues } from "react-hook-form";
import type { z } from "zod";
import { leerError } from "../api/cliente";

// eslint-disable-next-line @typescript-eslint/no-explicit-any
export type Campo<T = any> = {
  nombre: Extract<keyof T, string>;
  etiqueta: string;
  tipo?:
    | "texto"
    | "email"
    | "numero"
    | "pesos"
    | "porcentaje"
    | "seleccion"
    | "multiseleccion"
    | "fecha"
    | "fechaHora"
    | "area"
    | "interruptor"
    | "contrasena";
  opciones?: { valor: string | number; etiqueta: string }[];
  ayuda?: string;
  ancho?: "completo" | "medio" | "tercio";
  deshabilitado?: boolean;
  mostrar?: (valores: T) => boolean;
};

type Props<S extends z.ZodType> = {
  abierto: boolean;
  titulo: string;
  descripcion?: ReactNode;
  esquema: S;
  campos: Campo<z.input<S>>[];
  valoresIniciales: Partial<z.input<S>>;
  textoGuardar?: string;
  alCerrar: () => void;
  alGuardar: (datos: z.output<S>) => Promise<unknown>;
  ancho?: "sm" | "md";
};

const columnas = {
  completo: "span 6",
  medio: { xs: "span 6", sm: "span 3" },
  tercio: { xs: "span 6", sm: "span 2" },
};

/**
 * Formulario en diálogo, validado con Zod (las mismas reglas que valida la API)
 * Los errores 422 de Laravel se muestran junto a cada campo
 */
export function FormularioDialogo<S extends z.ZodType>({
  abierto,
  titulo,
  descripcion,
  esquema,
  campos,
  valoresIniciales,
  textoGuardar = "Guardar",
  alCerrar,
  alGuardar,
  ancho = "sm",
}: Props<S>) {
  const { control, handleSubmit, reset, watch, setError, formState } =
    useForm<FieldValues>({
      resolver: zodResolver(esquema as never) as never,
      defaultValues: valoresIniciales as FieldValues,
    });
  const [errorGeneral, setErrorGeneral] = useState<string | null>(null);
  const valores = watch();

  useEffect(() => {
    if (abierto) {
      reset(valoresIniciales as FieldValues);
      setErrorGeneral(null);
    }
  }, [abierto]); // eslint-disable-line react-hooks/exhaustive-deps

  const enviar = handleSubmit(async (datos) => {
    setErrorGeneral(null);
    try {
      await alGuardar(datos as z.output<S>);
    } catch (e) {
      const err = leerError(e);
      let asignado = false;
      for (const [campo, mensajes] of Object.entries(err.errores ?? {})) {
        if (campos.some((c) => c.nombre === campo)) {
          setError(campo, { message: mensajes[0] });
          asignado = true;
        }
      }
      if (!asignado) setErrorGeneral(err.mensaje);
    }
  });

  return (
    <Dialog
      open={abierto}
      onClose={formState.isSubmitting ? undefined : alCerrar}
      fullWidth
      maxWidth={ancho}
    >
      <form onSubmit={enviar} noValidate>
        <DialogTitle>{titulo}</DialogTitle>
        <DialogContent>
          {descripcion && (
            <Box sx={{ mb: 2, color: "text.secondary", fontSize: "0.9rem" }}>
              {descripcion}
            </Box>
          )}
          {errorGeneral && (
            <Alert severity="error" sx={{ mb: 2 }}>
              {errorGeneral}
            </Alert>
          )}
          <Box
            sx={{
              display: "grid",
              gridTemplateColumns: "repeat(6, 1fr)",
              gap: 2,
              pt: 1,
            }}
          >
            {campos
              .filter((c) => !c.mostrar || c.mostrar(valores as z.input<S>))
              .map((c) => (
                <Box
                  key={c.nombre}
                  sx={{ gridColumn: columnas[c.ancho ?? "completo"] }}
                >
                  <Controller
                    name={c.nombre}
                    control={control}
                    render={({ field, fieldState }) => {
                      if (c.tipo === "interruptor") {
                        return (
                          <FormControlLabel
                            control={
                              <Switch
                                checked={!!field.value}
                                onChange={(e) =>
                                  field.onChange(e.target.checked)
                                }
                                disabled={c.deshabilitado}
                              />
                            }
                            label={c.etiqueta}
                          />
                        );
                      }
                      if (c.tipo === "multiseleccion") {
                        const valor =
                          (field.value as (string | number)[] | undefined) ??
                          [];
                        return (
                          <TextField
                            select
                            label={c.etiqueta}
                            value={valor}
                            onChange={(e) => field.onChange(e.target.value)}
                            error={!!fieldState.error}
                            helperText={fieldState.error?.message ?? c.ayuda}
                            slotProps={{
                              select: {
                                multiple: true,
                                renderValue: (sel) =>
                                  (sel as (string | number)[])
                                    .map(
                                      (v) =>
                                        c.opciones?.find((o) => o.valor === v)
                                          ?.etiqueta ?? v,
                                    )
                                    .join(", "),
                              },
                            }}
                          >
                            {c.opciones?.map((o) => (
                              <MenuItem key={o.valor} value={o.valor}>
                                <Checkbox
                                  size="small"
                                  checked={valor.includes(o.valor)}
                                  sx={{ p: 0, mr: 1 }}
                                />
                                {o.etiqueta}
                              </MenuItem>
                            ))}
                          </TextField>
                        );
                      }
                      const numerico =
                        c.tipo === "numero" ||
                        c.tipo === "pesos" ||
                        c.tipo === "porcentaje";
                      return (
                        <TextField
                          {...field}
                          value={field.value ?? ""}
                          onChange={(e) => {
                            const v = e.target.value;
                            field.onChange(
                              numerico ? (v === "" ? "" : Number(v)) : v,
                            );
                          }}
                          label={c.etiqueta}
                          select={c.tipo === "seleccion"}
                          multiline={c.tipo === "area"}
                          minRows={c.tipo === "area" ? 3 : undefined}
                          type={
                            numerico
                              ? "number"
                              : c.tipo === "fecha"
                                ? "date"
                                : c.tipo === "fechaHora"
                                  ? "datetime-local"
                                  : c.tipo === "email"
                                    ? "email"
                                    : c.tipo === "contrasena"
                                      ? "password"
                                      : "text"
                          }
                          disabled={c.deshabilitado}
                          error={!!fieldState.error}
                          helperText={fieldState.error?.message ?? c.ayuda}
                          slotProps={{
                            inputLabel:
                              c.tipo === "fecha" || c.tipo === "fechaHora"
                                ? { shrink: true }
                                : undefined,
                            input:
                              c.tipo === "pesos"
                                ? {
                                    startAdornment: (
                                      <InputAdornment position="start">
                                        $
                                      </InputAdornment>
                                    ),
                                  }
                                : c.tipo === "porcentaje"
                                  ? {
                                      endAdornment: (
                                        <InputAdornment position="end">
                                          %
                                        </InputAdornment>
                                      ),
                                    }
                                  : undefined,
                          }}
                        >
                          {c.tipo === "seleccion" &&
                            c.opciones?.map((o) => (
                              <MenuItem key={o.valor} value={o.valor}>
                                {o.etiqueta}
                              </MenuItem>
                            ))}
                        </TextField>
                      );
                    }}
                  />
                </Box>
              ))}
          </Box>
        </DialogContent>
        <DialogActions sx={{ px: 3, pb: 2.5 }}>
          <Button
            onClick={alCerrar}
            color="inherit"
            disabled={formState.isSubmitting}
          >
            Cancelar
          </Button>
          <Button
            type="submit"
            variant="contained"
            disabled={formState.isSubmitting}
          >
            {formState.isSubmitting ? "Guardando…" : textoGuardar}
          </Button>
        </DialogActions>
      </form>
    </Dialog>
  );
}
