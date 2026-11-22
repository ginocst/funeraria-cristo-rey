import {
  Box,
  LinearProgress,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TablePagination,
  TableRow,
  Typography,
} from "@mui/material";
import type { ReactNode } from "react";

export type Columna<T> = {
  id: string;
  titulo: string;
  render: (fila: T) => ReactNode;
  alinear?: "left" | "right" | "center";
  ancho?: number | string;
  ocultarEnMovil?: boolean;
};

type Props<T> = {
  columnas: Columna<T>[];
  filas: T[] | undefined;
  clave: (fila: T) => string | number;
  cargando?: boolean;
  vacio?: ReactNode;
  alClic?: (fila: T) => void;
  paginacion?: {
    pagina: number;
    total: number;
    porPagina: number;
    alCambiar: (pagina: number) => void;
  };
  densa?: boolean;
};

/** Tabla de datos con estado de carga, mensaje vacío y paginación del servidor */
export function Tabla<T>({
  columnas,
  filas,
  clave,
  cargando,
  vacio,
  alClic,
  paginacion,
  densa,
}: Props<T>) {
  return (
    <Box sx={{ position: "relative" }}>
      {cargando && (
        <LinearProgress
          sx={{
            position: "absolute",
            top: 0,
            left: 0,
            right: 0,
            zIndex: 2,
            height: 2,
          }}
        />
      )}
      <TableContainer>
        <Table
          size={densa ? "small" : "medium"}
          sx={{ "& td, & th": { py: densa ? 1 : 1.4 } }}
        >
          <TableHead>
            <TableRow>
              {columnas.map((c) => (
                <TableCell
                  key={c.id}
                  align={c.alinear}
                  sx={{
                    width: c.ancho,
                    whiteSpace: "nowrap",
                    display: c.ocultarEnMovil
                      ? { xs: "none", md: "table-cell" }
                      : undefined,
                  }}
                >
                  {c.titulo}
                </TableCell>
              ))}
            </TableRow>
          </TableHead>
          <TableBody>
            {filas?.map((f) => (
              <TableRow
                key={clave(f)}
                hover={!!alClic}
                onClick={alClic ? () => alClic(f) : undefined}
                onKeyDown={
                  alClic ? (e) => e.key === "Enter" && alClic(f) : undefined
                }
                tabIndex={alClic ? 0 : undefined}
                sx={{
                  cursor: alClic ? "pointer" : undefined,
                  "&:last-child td": { borderBottom: 0 },
                }}
              >
                {columnas.map((c) => (
                  <TableCell
                    key={c.id}
                    align={c.alinear}
                    sx={{
                      display: c.ocultarEnMovil
                        ? { xs: "none", md: "table-cell" }
                        : undefined,
                    }}
                  >
                    {c.render(f)}
                  </TableCell>
                ))}
              </TableRow>
            ))}
          </TableBody>
        </Table>
      </TableContainer>
      {!cargando && filas?.length === 0 && (
        <Box sx={{ py: 6, px: 3, textAlign: "center" }}>
          {typeof vacio === "string" || !vacio ? (
            <Typography color="text.secondary">
              {vacio ?? "No hay registros que coincidan con la búsqueda."}
            </Typography>
          ) : (
            vacio
          )}
        </Box>
      )}
      {paginacion && paginacion.total > paginacion.porPagina && (
        <TablePagination
          component="div"
          count={paginacion.total}
          page={paginacion.pagina - 1}
          rowsPerPage={paginacion.porPagina}
          rowsPerPageOptions={[]}
          onPageChange={(_, p) => paginacion.alCambiar(p + 1)}
          labelDisplayedRows={({ from, to, count }) =>
            `${from}–${to} de ${count}`
          }
        />
      )}
    </Box>
  );
}

/** Texto principal con una línea secundaria debajo (nombre y RUT, código y fecha, etc.) */
export function DosLineas({
  principal,
  secundaria,
}: {
  principal: ReactNode;
  secundaria?: ReactNode;
}) {
  return (
    <Box sx={{ minWidth: 0 }}>
      <Typography variant="body2" sx={{ fontWeight: 600 }}>
        {principal}
      </Typography>
      {secundaria && (
        <Typography
          variant="body2"
          color="text.secondary"
          sx={{ fontSize: "0.8rem" }}
        >
          {secundaria}
        </Typography>
      )}
    </Box>
  );
}
