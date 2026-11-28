import { useQuery } from "@tanstack/react-query";
import { api, type Pagina } from "../../api/cliente";
import type { CategoriaOrganizacion, Organizacion } from "../../api/tipos";

/** Listas completas para los selectores de los formularios */
const todas = async <T>(recurso: string) =>
  (await api.get<Pagina<T>>(`/${recurso}`, { params: { por_pagina: 200 } }))
    .data.data;

export const useOrganizaciones = () =>
  useQuery({
    queryKey: ["organizaciones", "todas"],
    queryFn: () => todas<Organizacion>("organizaciones"),
  });

export const useCategorias = () =>
  useQuery({
    queryKey: ["categorias-organizacion", "todas"],
    queryFn: () => todas<CategoriaOrganizacion>("categorias-organizacion"),
  });
