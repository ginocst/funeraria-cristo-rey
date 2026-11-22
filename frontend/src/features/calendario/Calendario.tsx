import FullCalendar from '@fullcalendar/react'
import dayGridPlugin from '@fullcalendar/react/daygrid'
import listPlugin from '@fullcalendar/react/list'
import esLocale from '@fullcalendar/react/locales/es'
import classicThemePlugin from '@fullcalendar/react/themes/classic'
import timeGridPlugin from '@fullcalendar/react/timegrid'
import '@fullcalendar/react/skeleton.css'
import '@fullcalendar/react/themes/classic/theme.css'
import '@fullcalendar/react/themes/classic/palette.css'
import { Box, Stack, Typography } from '@mui/material'
import { useQuery } from '@tanstack/react-query'
import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { api } from '../../api/cliente'
import type { EventoCalendario } from '../../api/tipos'
import { colores } from '../../app/tema'
import { Encabezado } from '../../components/Encabezado'
import { Seccion } from '../../components/Seccion'
import { aLocal } from '../../lib/formato'

const COLORES: Record<string, { fondo: string; texto: string; borde: string; etiqueta: string }> = {
  velatorio: { fondo: colores.vinoSuave, texto: colores.vinoProfundo, borde: colores.rosaCeniza, etiqueta: 'Velatorio' },
  traslado: { fondo: colores.vino, texto: '#fff', borde: colores.vino, etiqueta: 'Traslado' },
  misa: { fondo: '#E4F1F5', texto: '#174F63', borde: '#B9DCE7', etiqueta: 'Misa o responso' },
  retiro: { fondo: '#EFECEE', texto: colores.tinta, borde: '#D8D1D4', etiqueta: 'Retiro' },
  otra: { fondo: '#EFECEE', texto: colores.tinta, borde: '#D8D1D4', etiqueta: 'Otra actividad' },
}

/** Calendario de velatorios, traslados y actividades. Las solicitudes aún no aceptadas se ven con borde punteado */
export default function Calendario() {
  const navegar = useNavigate()
  const [rango, setRango] = useState<{ desde: string; hasta: string } | null>(null)
  const [vista, setVista] = useState('timeGridWeek')
  const { data } = useQuery({
    queryKey: ['calendario', rango],
    queryFn: async () => (await api.get<EventoCalendario[]>('/calendario', { params: rango ?? undefined })).data,
    enabled: !!rango,
  })

  const eventos = (data ?? []).map((e) => {
    const c = COLORES[e.tipo] ?? COLORES.otra
    const propuesto = e.estado === 'en_revision' || e.estado === 'con_observaciones'
    // En la vista por horas el velatorio se muestra como fondo tenue
    // para que los traslados, misas y retiros se lean sin superponerse
    const fondo = e.tipo === 'velatorio' && vista.startsWith('timeGrid')
    return {
      id: e.id,
      title: `${e.titulo}${e.lugar ? `, ${e.lugar}` : ''}`,
      start: aLocal(e.inicio)!.format('YYYY-MM-DDTHH:mm:ss'),
      end: e.fin ? aLocal(e.fin)!.format('YYYY-MM-DDTHH:mm:ss') : undefined,
      color: c.fondo,
      contrastColor: c.texto,
      display: fondo ? 'background' : 'auto',
      className: [propuesto ? 'evento-propuesto' : '', fondo ? 'evento-fondo' : ''].join(' ').trim() || undefined,
      extendedProps: e,
    }
  })

  return (
    <>
      <Encabezado titulo="Calendario" descripcion="Velatorios, traslados y actividades de todos los servicios en hora local." />
      <Stack direction="row" sx={{ mb: 2, flexWrap: 'wrap', gap: 2 }}>
        {Object.entries(COLORES).slice(0, 4).map(([k, c]) => (
          <Stack key={k} direction="row" spacing={0.75} sx={{ alignItems: 'center' }}>
            <Box sx={{ width: 14, height: 10, borderRadius: 0.5, bgcolor: c.fondo, border: `1px solid ${c.borde}` }} />
            <Typography variant="caption">{c.etiqueta}</Typography>
          </Stack>
        ))}
        <Stack direction="row" spacing={0.75} sx={{ alignItems: 'center' }}>
          <Box sx={{ width: 14, height: 10, borderRadius: 0.5, border: `1.5px dashed ${colores.vino}` }} />
          <Typography variant="caption">Solicitud por evaluar</Typography>
        </Stack>
      </Stack>
      <Seccion sx={{
        ...({
          '--fc-classic-today': colores.vinoTenue,
          '--fc-classic-primary': colores.vino,
          '--fc-classic-primary-foreground': '#fff',
          '--fc-classic-button': '#fff',
          '--fc-classic-button-foreground': colores.tinta,
          '--fc-classic-button-border': colores.linea,
          '--fc-classic-button-strong': colores.vino,
          '--fc-classic-button-strong-border': colores.vino,
          '--fc-classic-border': colores.linea,
          '--fc-classic-now': colores.serie2,
          '--fc-classic-background-event': colores.rosaCeniza,
          '--fc-classic-background-event-opacity': 0.3,
        } as object),
        fontSize: '0.85rem',
        '& .evento-fondo, & .evento-fondo *': { color: 'transparent !important' },
        '& .evento-propuesto': { borderStyle: 'dashed !important', borderWidth: '1.5px !important', opacity: 0.85 },
        '& .fc-event': { cursor: 'pointer' },
        '& .fc-toolbar-title': { fontFamily: '"Cormorant Garamond", serif', fontWeight: 600, textTransform: 'capitalize' },
      }}>
        <FullCalendar
          plugins={[dayGridPlugin, timeGridPlugin, listPlugin, classicThemePlugin]}
          locale={esLocale}
          initialView="timeGridWeek"
          headerToolbar={{ start: 'prev,next today', center: 'title', end: 'dayGridMonth,timeGridWeek,listWeek' }}
          height="auto"
          slotMinTime="00:00:00"
          slotMaxTime="24:00:00"
          allDaySlot={false}
          nowIndicator
          events={eventos}
          datesSet={(i) => {
            setRango({ desde: i.start.toISOString().slice(0, 10), hasta: i.end.toISOString().slice(0, 10) })
            setVista(i.view.type)
          }}
          eventClick={(i) => {
            const e = i.event.extendedProps as EventoCalendario
            navegar(e.solicitud_id ? `/solicitudes/${e.solicitud_id}` : `/expedientes/${e.expediente_id}`)
          }}
        />
      </Seccion>
    </>
  )
}