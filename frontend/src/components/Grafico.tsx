import { Box } from "@mui/material";
import type { EChartsOption } from "echarts";
import { BarChart, LineChart } from "echarts/charts";
import {
  GridComponent,
  LegendComponent,
  TooltipComponent,
} from "echarts/components";
import * as echarts from "echarts/core";
import { SVGRenderer } from "echarts/renderers";
import { useEffect, useRef } from "react";
import { fuenteTexto } from "../app/tema";

echarts.use([
  BarChart,
  LineChart,
  GridComponent,
  LegendComponent,
  TooltipComponent,
  SVGRenderer,
]);

/** Contenedor mínimo de ECharts (renderizado SVG, se adapta al ancho) */
export function Grafico({
  opcion,
  alto = 260,
  etiqueta,
}: {
  opcion: EChartsOption;
  alto?: number;
  etiqueta: string;
}) {
  const ref = useRef<HTMLDivElement>(null);
  const grafico = useRef<echarts.ECharts | null>(null);

  useEffect(() => {
    if (!ref.current) return;
    const g = echarts.init(ref.current, undefined, { renderer: "svg" });
    grafico.current = g;
    const observador = new ResizeObserver(() => g.resize());
    observador.observe(ref.current);
    return () => {
      observador.disconnect();
      g.dispose();
    };
  }, []);

  useEffect(() => {
    grafico.current?.setOption(
      {
        textStyle: { fontFamily: fuenteTexto },
        animationDuration: 500,
        ...opcion,
      },
      true,
    );
  }, [opcion]);

  return (
    <Box
      ref={ref}
      role="img"
      aria-label={etiqueta}
      sx={{ width: "100%", height: alto }}
    />
  );
}
