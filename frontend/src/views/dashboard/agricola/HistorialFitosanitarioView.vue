<template>
  <div class="hf-page fade-in">
    <!-- BARRA CONTEXTO -->
    <div class="cbar">
      <div class="bread">
        <i class="ti ti-home" style="font-size:14px"></i>
        <span>›</span>
        <span>Módulo Agrícola</span>
        <span>›</span>
        <span class="cur">Historial Fitosanitario</span>
      </div>

      <div class="cbar-right">
        <button
          v-if="historial && !loadingHistorial"
          class="btn btn-s"
          type="button"
          @click="imprimirHistorial"
        >
          <i class="ti ti-printer"></i>
          Imprimir historial
        </button>

        <button
          class="btn btn-s"
          type="button"
          :disabled="loadingParcelas"
          @click="fetchParcelas"
        >
          <i class="ti ti-refresh" :class="{ 'hf-spin': loadingParcelas }"></i>
          {{ loadingParcelas ? 'Actualizando...' : 'Actualizar' }}
        </button>
      </div>
    </div>

    <!-- HEADER -->
    <div class="hf-hero">
      <div class="hf-hero-content">
        <div class="hf-badge">
          <i class="ti ti-history"></i>
          Seguimiento técnico por parcela
        </div>

        <h1>Historial Fitosanitario de Parcelas</h1>

        <p>
          Consulta el recorrido completo de una parcela por gestión agrícola y campaña:
          cultivos, inspecciones, diagnósticos CNN, alertas fitosanitarias y cosechas.
        </p>
      </div>

      <div class="hf-hero-icon">
        <i class="ti ti-plant-2"></i>
      </div>
    </div>

    <!-- ERROR -->
    <div v-if="pageError" class="hf-error">
      <i class="ti ti-alert-circle"></i>
      <span>{{ pageError }}</span>
    </div>

    <!-- LAYOUT PRINCIPAL -->
    <div class="hf-layout">
      <!-- PANEL IZQUIERDO -->
      <aside class="hf-sidebar card">
        <div class="hf-side-header">
          <div>
            <h3>Parcelas</h3>
            <p>Selecciona una parcela para revisar su historial.</p>
          </div>
        </div>

        <div class="hf-search">
          <i class="ti ti-search"></i>
          <input v-model="search" placeholder="Buscar parcela o productor..." />
        </div>

        <div class="hf-filter-row">
          <button
            type="button"
            class="hf-filter-chip"
            :class="{ active: filtroEstado === '' }"
            @click="filtroEstado = ''"
          >
            Todas
          </button>

          <button
            type="button"
            class="hf-filter-chip"
            :class="{ active: filtroEstado === 'Activa' }"
            @click="filtroEstado = 'Activa'"
          >
            Activas
          </button>

          <button
            type="button"
            class="hf-filter-chip"
            :class="{ active: filtroEstado === 'En_descanso' }"
            @click="filtroEstado = 'En_descanso'"
          >
            Descanso
          </button>
        </div>

        <!-- FILTRO POR GESTIÓN Y CAMPAÑA -->
        <div class="hf-year-filter">
          <label>
            <i class="ti ti-calendar-stats"></i>
            Gestión agrícola
          </label>

          <select v-model="selectedAnio" @change="recargarHistorial">
            <option
              v-for="anio in aniosDisponibles"
              :key="anio"
              :value="anio"
            >
              {{ anio }}
            </option>
          </select>

          <select v-model="selectedCampania" @change="recargarHistorial">
            <option value="todas">Todas las campañas</option>
            <option value="primera">Primera campaña</option>
            <option value="segunda">Segunda campaña</option>
          </select>

          <small>
            El historial se muestra por gestión y campaña para evitar datos mezclados
            de diferentes siembras.
          </small>
        </div>

        <div v-if="loadingParcelas" class="hf-loading-box">
          <div class="spinner"></div>
          <span>Cargando parcelas...</span>
        </div>

        <div v-else class="hf-parcelas-list">
          <button
            v-for="p in filteredParcelas"
            :key="p.id"
            type="button"
            class="hf-parcela-card"
            :class="{ active: Number(selectedParcelaId) === Number(p.id) }"
            @click="seleccionarParcela(p)"
          >
            <div class="hf-parcela-avatar">
              <i class="ti ti-map-pin"></i>
            </div>

            <div class="hf-parcela-main">
              <strong>{{ p.codigo_parcela }}</strong>
              <span>{{ p.nombre_referencial || 'Sin nombre referencial' }}</span>
              <small>{{ nombreProductor(p.productor) }}</small>
            </div>

            <span class="hf-status" :class="badgeEstado(p.estado)">
              {{ limpiarEstado(p.estado) }}
            </span>
          </button>

          <div v-if="!filteredParcelas.length" class="hf-empty-mini">
            <i class="ti ti-map-off"></i>
            <span>No se encontraron parcelas.</span>
          </div>
        </div>
      </aside>

      <!-- PANEL DERECHO -->
      <main class="hf-content">
        <!-- ESTADO INICIAL -->
        <div v-if="!selectedParcelaId && !loadingHistorial" class="hf-placeholder card">
          <div class="hf-placeholder-icon">
            <i class="ti ti-clipboard-search"></i>
          </div>

          <h2>Selecciona una parcela</h2>

          <p>
            El historial fitosanitario se cargará únicamente cuando elijas una parcela.
            Esto evita cargar información innecesaria y mantiene el sistema más rápido.
          </p>
        </div>

        <!-- CARGANDO HISTORIAL -->
        <div v-if="loadingHistorial" class="hf-placeholder card">
          <div class="spinner"></div>

          <h2>Cargando historial...</h2>

          <p>
            Consultando cultivos, inspecciones, diagnósticos, alertas y cosechas.
          </p>
        </div>

        <template v-if="historial && !loadingHistorial">
          <!-- TARJETA RESUMEN PARCELA -->
          <section class="hf-parcela-resumen card">
            <div class="hf-resumen-left">
              <div class="hf-resumen-icon">
                <i class="ti ti-map-2"></i>
              </div>

              <div>
                <div class="hf-resumen-label">Parcela seleccionada</div>
                <h2>{{ parcela.codigo_parcela || '—' }}</h2>
                <p>{{ parcela.nombre_referencial || 'Sin nombre referencial' }}</p>
              </div>
            </div>

            <div class="hf-resumen-right">
              <div>
                <span>Productor</span>
                <strong>{{ parcela.productor?.nombre_completo || '—' }}</strong>
              </div>

              <div>
                <span>Superficie</span>
                <strong>{{ parcela.superficie_ha ? parcela.superficie_ha + ' ha' : '—' }}</strong>
              </div>

              <div>
                <span>Estado</span>
                <b class="hf-status" :class="badgeEstado(parcela.estado)">
                  {{ limpiarEstado(parcela.estado) }}
                </b>
              </div>

              <div>
                <span>Gestión</span>
                <strong>
                  {{ historial?.filtros?.nombre_gestion || 'Gestión agrícola ' + selectedAnio }}
                </strong>
              </div>

              <div>
                <span>Campaña</span>
                <strong>
                  {{ historial?.filtros?.nombre_campania || nombreCampania }}
                </strong>
              </div>
            </div>
          </section>

          <!-- KPIS -->
          <section class="hf-kpi-grid">
            <div class="hf-kpi-card hf-kpi-green">
              <div class="hf-kpi-icon">
                <i class="ti ti-seeding"></i>
              </div>

              <div>
                <span>Cultivos</span>
                <strong>{{ resumen.total_cultivos || 0 }}</strong>
                <small>registrados</small>
              </div>
            </div>

            <div class="hf-kpi-card hf-kpi-blue">
              <div class="hf-kpi-icon">
                <i class="ti ti-clipboard-check"></i>
              </div>

              <div>
                <span>Inspecciones</span>
                <strong>{{ resumen.total_inspecciones || 0 }}</strong>
                <small>visitas técnicas</small>
              </div>
            </div>

            <div class="hf-kpi-card hf-kpi-purple">
              <div class="hf-kpi-icon">
                <i class="ti ti-microscope"></i>
              </div>

              <div>
                <span>Diagnósticos</span>
                <strong>{{ resumen.total_diagnosticos || 0 }}</strong>
                <small>análisis CNN</small>
              </div>
            </div>

            <div class="hf-kpi-card hf-kpi-red">
              <div class="hf-kpi-icon">
                <i class="ti ti-bell"></i>
              </div>

              <div>
                <span>Alertas</span>
                <strong>{{ resumen.total_alertas || 0 }}</strong>
                <small>{{ resumen.alertas_pendientes || 0 }} pendientes</small>
              </div>
            </div>
          </section>

          <!-- RESUMEN TÉCNICO -->
          <section class="hf-management-summary card">
            <div class="hf-management-head">
              <div>
                <span class="hf-management-label">
                  <i class="ti ti-report-analytics"></i>
                  Resumen técnico de la gestión
                </span>

                <h3>
                  {{ historial?.filtros?.nombre_gestion || 'Gestión agrícola ' + selectedAnio }}
                  ·
                  {{ historial?.filtros?.nombre_campania || nombreCampania }}
                </h3>

                <p>
                  Vista resumida para interpretar rápidamente el estado sanitario de la parcela,
                  sin revisar todos los registros detallados.
                </p>
              </div>

              <span class="hf-risk-pill" :class="badgeRiesgo(resumenTecnico.riesgoPrincipal)">
                {{ resumenTecnico.riesgoPrincipal }}
              </span>
            </div>

            <div class="hf-summary-grid">
              <article class="hf-summary-item">
                <span>Actividad registrada</span>
                <strong>{{ resumenTecnico.actividad }}</strong>
                <small>Cultivos, inspecciones y diagnósticos de la campaña.</small>
              </article>

              <article class="hf-summary-item">
                <span>Enfermedad principal</span>
                <strong>{{ resumenTecnico.enfermedadPrincipal }}</strong>
                <small>Resultado más repetido en los diagnósticos.</small>
              </article>

              <article class="hf-summary-item">
                <span>Estado del seguimiento</span>
                <strong>{{ resumenTecnico.estadoSeguimiento }}</strong>
                <small>Situación actual según alertas e inspecciones.</small>
              </article>

              <article class="hf-summary-item hf-summary-wide">
                <span>Última recomendación técnica</span>
                <strong>{{ resumenTecnico.ultimaRecomendacion }}</strong>
                <small>Recomendación u observación más reciente registrada.</small>
              </article>
            </div>
          </section>

          <!-- INFORMACIÓN GENERAL -->
          <section class="hf-section card">
            <div class="hf-section-head">
              <div>
                <h3>
                  <i class="ti ti-info-circle"></i>
                  Información general
                </h3>
                <p>Datos productivos y ubicación de la parcela.</p>
              </div>
            </div>

            <div class="hf-info-grid">
              <div>
                <label>Código</label>
                <strong>{{ parcela.codigo_parcela || '—' }}</strong>
              </div>

              <div>
                <label>Nombre referencial</label>
                <strong>{{ parcela.nombre_referencial || '—' }}</strong>
              </div>

              <div>
                <label>Productor</label>
                <strong>{{ parcela.productor?.nombre_completo || '—' }}</strong>
              </div>

              <div>
                <label>Superficie</label>
                <strong>{{ parcela.superficie_ha ? parcela.superficie_ha + ' ha' : '—' }}</strong>
              </div>

              <div>
                <label>Tipo de suelo</label>
                <strong>{{ parcela.tipo_suelo || '—' }}</strong>
              </div>

              <div>
                <label>Sistema de riego</label>
                <strong>{{ parcela.sistema_riego || '—' }}</strong>
              </div>

              <div>
                <label>Altitud</label>
                <strong>{{ parcela.altitud_msnm ? parcela.altitud_msnm + ' msnm' : '—' }}</strong>
              </div>

              <div>
                <label>Coordenadas</label>
                <strong>
                  {{
                    parcela.latitud && parcela.longitud
                      ? parcela.latitud + ', ' + parcela.longitud
                      : parcela.coordenadas_texto || '—'
                  }}
                </strong>
              </div>
            </div>
          </section>

          <!-- LÍNEA DE TIEMPO -->
          <section class="hf-section card">
            <div class="hf-section-head">
              <div>
                <h3>
                  <i class="ti ti-timeline"></i>
                  Línea de tiempo fitosanitaria
                </h3>
                <p>Resumen cronológico de inspecciones, diagnósticos, alertas y cosechas.</p>
              </div>
            </div>

            <div v-if="!timeline.length" class="hf-empty">
              <i class="ti ti-timeline-event-exclamation"></i>
              <span>
                No existe actividad fitosanitaria registrada para esta parcela en la gestión
                {{ selectedAnio }}.
              </span>
            </div>

            <div v-else class="hf-timeline hf-timeline-pro">
              <article
                v-for="item in timeline"
                :key="item.key"
                class="hf-event-card"
                :class="'hf-event-' + item.type"
              >
                <div class="hf-event-date">
                  <strong>{{ diaFecha(item.date) }}</strong>
                  <span>{{ mesFecha(item.date) }}</span>
                </div>

                <div class="hf-event-icon" :class="item.type">
                  <i :class="item.icon"></i>
                </div>

                <div class="hf-event-body">
                  <div class="hf-event-top">
                    <div>
                      <span class="hf-event-label">{{ item.label }}</span>
                      <h4>{{ item.title }}</h4>
                      <p>{{ item.subtitle }}</p>
                    </div>

                    <span class="hf-status" :class="badgeRiesgo(item.risk)">
                      {{ item.risk }}
                    </span>
                  </div>

                  <div class="hf-event-result">
                    <strong>Resultado:</strong>
                    <span>{{ item.result }}</span>
                  </div>

                  <div class="hf-event-result">
                    <strong>Recomendación:</strong>
                    <span>{{ item.recommendation }}</span>
                  </div>

                  <div v-if="item.chips?.length" class="hf-event-chips">
                    <span v-for="chip in item.chips" :key="chip">
                      {{ chip }}
                    </span>
                  </div>
                </div>
              </article>
            </div>
          </section>

          <!-- BOTÓN DETALLE COMPLETO -->
          <section class="hf-detail-switch card">
            <div>
              <h3>
                <i class="ti ti-database"></i>
                Detalle completo del historial
              </h3>

              <p>
                Las tablas completas se ocultan para mantener el historial limpio.
                Puedes abrirlas cuando necesites revisar cultivos, inspecciones,
                diagnósticos, alertas y cosechas.
              </p>
            </div>

            <button type="button" class="hf-detail-btn" @click="mostrarDetalle = !mostrarDetalle">
              <i :class="mostrarDetalle ? 'ti ti-eye-off' : 'ti ti-eye'"></i>
              {{ mostrarDetalle ? 'Ocultar detalle' : 'Ver detalle completo' }}
            </button>
          </section>

          <!-- CULTIVOS -->
          <section v-if="mostrarDetalle" class="hf-section card">
            <div class="hf-section-head">
              <div>
                <h3>
                  <i class="ti ti-seeding"></i>
                  Cultivos registrados
                </h3>
                <p>Variedades sembradas y estado productivo.</p>
              </div>

              <span class="hf-count">{{ cultivos.length }} registros</span>
            </div>

            <div v-if="!cultivos.length" class="hf-empty">
              <i class="ti ti-plant-off"></i>
              <span>No hay cultivos registrados en la gestión {{ selectedAnio }}.</span>
            </div>

            <div v-else class="hf-table-wrap">
              <table class="tbl hf-table">
                <thead>
                  <tr>
                    <th>Variedad</th>
                    <th>Fecha siembra</th>
                    <th>Semilla</th>
                    <th>Etapa</th>
                    <th>Cosecha estimada</th>
                    <th>Estado</th>
                  </tr>
                </thead>

                <tbody>
                  <tr v-for="c in cultivos" :key="c.id">
                    <td>
                      <strong>{{ c.variedad_papa || '—' }}</strong>
                    </td>
                    <td>{{ formatDate(c.fecha_siembra) }}</td>
                    <td>{{ Number(c.cantidad_semilla_kg || 0).toFixed(1) }} kg</td>
                    <td>{{ c.etapa_fenologica?.nombre || c.etapa_fenologica?.descripcion || '—' }}</td>
                    <td>{{ formatDate(c.fecha_estimada_cosecha) }}</td>
                    <td>
                      <span class="hf-status" :class="badgeEstado(c.estado)">
                        {{ limpiarEstado(c.estado) }}
                      </span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </section>

          <!-- INSPECCIONES -->
          <section v-if="mostrarDetalle" class="hf-section card">
            <div class="hf-section-head">
              <div>
                <h3>
                  <i class="ti ti-clipboard-list"></i>
                  Inspecciones técnicas
                </h3>
                <p>Visitas realizadas en campo y condiciones encontradas.</p>
              </div>

              <span class="hf-count">{{ inspecciones.length }} registros</span>
            </div>

            <div v-if="!inspecciones.length" class="hf-empty">
              <i class="ti ti-clipboard-x"></i>
              <span>No hay inspecciones registradas en la gestión {{ selectedAnio }}.</span>
            </div>

            <div v-else class="hf-inspecciones-grid">
              <article
                v-for="i in inspecciones"
                :key="i.id"
                class="hf-inspeccion-card"
              >
                <div class="hf-inspeccion-head">
                  <div>
                    <strong>{{ formatDate(i.fecha_visita) }}</strong>
                    <span>{{ i.usuario?.nombre || i.usuario?.name || 'Usuario no registrado' }}</span>
                  </div>

                  <b class="hf-status" :class="badgeEstado(i.estado)">
                    {{ limpiarEstado(i.estado) }}
                  </b>
                </div>

                <div class="hf-inspeccion-data">
                  <div>
                    <label>Clima</label>
                    <span>{{ i.condicion_climatica || '—' }}</span>
                  </div>

                  <div>
                    <label>Temp.</label>
                    <span>{{ i.temperatura_c ?? '—' }} °C</span>
                  </div>

                  <div>
                    <label>Humedad</label>
                    <span>{{ i.humedad_relativa_pct ?? '—' }} %</span>
                  </div>

                  <div>
                    <label>Afectación</label>
                    <span>{{ i.pct_plantas_afectadas ?? '—' }} %</span>
                  </div>
                </div>

                <p v-if="i.observaciones">
                  {{ i.observaciones }}
                </p>

                <div class="hf-inspeccion-footer">
                  <i class="ti ti-microscope"></i>
                  {{ i.total_diagnosticos || 0 }} diagnósticos asociados
                </div>
              </article>
            </div>
          </section>

          <!-- DIAGNÓSTICOS -->
          <section v-if="mostrarDetalle" class="hf-section card">
            <div class="hf-section-head">
              <div>
                <h3>
                  <i class="ti ti-microscope"></i>
                  Diagnósticos CNN
                </h3>
                <p>Resultados obtenidos mediante análisis de imágenes.</p>
              </div>

              <span class="hf-count">{{ diagnosticos.length }} registros</span>
            </div>

            <div v-if="!diagnosticos.length" class="hf-empty">
              <i class="ti ti-photo-off"></i>
              <span>No hay diagnósticos registrados en la gestión {{ selectedAnio }}.</span>
            </div>

            <div v-else class="hf-diagnosticos-grid">
              <article
                v-for="d in diagnosticos"
                :key="d.id"
                class="hf-diagnostico-card"
              >
                <div class="hf-diagnostico-img">
                  <img
                    v-if="imagenDiagnostico(d)"
                    :src="imagenDiagnostico(d)"
                    alt="Imagen del diagnóstico"
                    @error="ocultarImagen"
                  />

                  <div v-else class="hf-img-empty">
                    <i class="ti ti-photo"></i>
                  </div>
                </div>

                <div class="hf-diagnostico-body">
                  <div class="hf-diagnostico-title">
                    <strong>
                      {{ d.enfermedad?.nombre || d.enfermedad?.nombre_comun || 'Sin enfermedad asignada' }}
                    </strong>

                    <span class="hf-status" :class="badgeEstado(d.estado_validacion)">
                      {{ limpiarEstado(d.estado_validacion) }}
                    </span>
                  </div>

                  <div class="hf-diagnostico-tags">
                    <span>
                      <i class="ti ti-percentage"></i>
                      {{ Number(d.porcentaje_confianza || 0).toFixed(1) }} %
                    </span>

                    <span>
                      <i class="ti ti-alert-triangle"></i>
                      {{ limpiarEstado(d.nivel_severidad) }}
                    </span>

                    <span>
                      <i class="ti ti-cpu"></i>
                      {{ d.modelo_version || 'Modelo CNN' }}
                    </span>
                  </div>

                  <p v-if="d.recomendacion">
                    {{ d.recomendacion }}
                  </p>

                  <small>
                    Inspección: {{ formatDate(d.fecha_inspeccion) }}
                  </small>
                </div>
              </article>
            </div>
          </section>

          <!-- ALERTAS -->
          <section v-if="mostrarDetalle" class="hf-section card">
            <div class="hf-section-head">
              <div>
                <h3>
                  <i class="ti ti-bell-ringing"></i>
                  Alertas fitosanitarias
                </h3>
                <p>Alertas generadas a partir de los diagnósticos registrados.</p>
              </div>

              <span class="hf-count">{{ alertas.length }} registros</span>
            </div>

            <div v-if="!alertas.length" class="hf-empty">
              <i class="ti ti-circle-check"></i>
              <span>No hay alertas fitosanitarias registradas en la gestión {{ selectedAnio }}.</span>
            </div>

            <div v-else class="hf-alertas-list">
              <article
                v-for="a in alertas"
                :key="a.id"
                class="hf-alerta-card"
              >
                <div class="hf-alerta-icon" :class="badgeRiesgo(a.nivel_riesgo)">
                  <i class="ti ti-alert-triangle"></i>
                </div>

                <div class="hf-alerta-body">
                  <div class="hf-alerta-top">
                    <div>
                      <strong>{{ a.titulo || 'Alerta fitosanitaria' }}</strong>
                      <span>{{ limpiarEstado(a.nivel_riesgo) }}</span>
                    </div>

                    <b class="hf-status" :class="badgeEstado(a.estado)">
                      {{ limpiarEstado(a.estado) }}
                    </b>
                  </div>

                  <p>{{ a.mensaje || 'Sin mensaje registrado.' }}</p>

                  <small>
                    Emitida: {{ formatDate(a.fecha_emision) }}
                    · Límite: {{ formatDate(a.fecha_limite_accion) }}
                  </small>
                </div>
              </article>
            </div>
          </section>

          <!-- COSECHAS -->
          <section v-if="mostrarDetalle" class="hf-section card">
            <div class="hf-section-head">
              <div>
                <h3>
                  <i class="ti ti-plant-2"></i>
                  Cosechas
                </h3>
                <p>Resultados productivos registrados para la parcela.</p>
              </div>

              <span class="hf-count">{{ cosechas.length }} registros</span>
            </div>

            <div v-if="!cosechas.length" class="hf-empty">
              <i class="ti ti-plant-off"></i>
              <span>No hay cosechas registradas en la gestión {{ selectedAnio }}.</span>
            </div>

            <div v-else class="hf-table-wrap">
              <table class="tbl hf-table">
                <thead>
                  <tr>
                    <th>Fecha</th>
                    <th>Cantidad</th>
                    <th>Rendimiento</th>
                    <th>Calidad</th>
                    <th>Destino</th>
                    <th>Ingreso estimado</th>
                  </tr>
                </thead>

                <tbody>
                  <tr v-for="c in cosechas" :key="c.id">
                    <td>{{ formatDate(c.fecha_cosecha) }}</td>
                    <td>{{ Number(c.cantidad_toneladas || 0).toFixed(2) }} t</td>
                    <td>{{ Number(c.rendimiento_t_ha || 0).toFixed(2) }} t/ha</td>
                    <td>{{ c.calidad || '—' }}</td>
                    <td>{{ c.destino || '—' }}</td>
                    <td>{{ formatoBs(c.ingreso_estimado_bs) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </section>
        </template>
      </main>
    </div>

    <!-- REPORTE LIMPIO SOLO PARA IMPRESIÓN -->
    <section v-if="historial && !loadingHistorial" class="hf-print-only">
      <header class="hf-print-clean-header">
        <div>
          <span>Sistema PROINPA · Gestión Agrícola IA</span>
          <h1>Historial Fitosanitario de Parcela</h1>
          <p>
            Reporte técnico para seguimiento agrícola, diagnóstico fitosanitario
            y control de alertas por gestión y campaña.
          </p>
        </div>

        <div class="hf-print-clean-meta">
          <strong>{{ parcela.codigo_parcela || '—' }}</strong>
          <span>{{ historial?.filtros?.nombre_gestion || 'Gestión agrícola ' + selectedAnio }}</span>
          <span>{{ historial?.filtros?.nombre_campania || nombreCampania }}</span>
          <span>Fecha: {{ formatDate(new Date()) }}</span>
        </div>
      </header>

      <section class="hf-print-block">
        <h2>Resumen general</h2>

        <div class="hf-print-kpis">
          <div>
            <span>Cultivos</span>
            <strong>{{ resumen.total_cultivos || 0 }}</strong>
          </div>

          <div>
            <span>Inspecciones</span>
            <strong>{{ resumen.total_inspecciones || 0 }}</strong>
          </div>

          <div>
            <span>Diagnósticos</span>
            <strong>{{ resumen.total_diagnosticos || 0 }}</strong>
          </div>

          <div>
            <span>Alertas</span>
            <strong>{{ resumen.total_alertas || 0 }}</strong>
          </div>
        </div>
      </section>

      <section class="hf-print-block">
        <h2>Resumen técnico</h2>

        <div class="hf-print-summary">
          <div>
            <span>Actividad registrada</span>
            <strong>{{ resumenTecnico.actividad }}</strong>
          </div>

          <div>
            <span>Enfermedad principal</span>
            <strong>{{ resumenTecnico.enfermedadPrincipal }}</strong>
          </div>

          <div>
            <span>Riesgo principal</span>
            <strong>{{ resumenTecnico.riesgoPrincipal }}</strong>
          </div>

          <div>
            <span>Estado del seguimiento</span>
            <strong>{{ resumenTecnico.estadoSeguimiento }}</strong>
          </div>
        </div>

        <div class="hf-print-recommendation">
          <span>Última recomendación técnica</span>
          <strong>{{ resumenTecnico.ultimaRecomendacion }}</strong>
        </div>
      </section>

      <section class="hf-print-block">
        <h2>Información de la parcela</h2>

        <div class="hf-print-info">
          <div>
            <span>Código</span>
            <strong>{{ parcela.codigo_parcela || '—' }}</strong>
          </div>

          <div>
            <span>Nombre referencial</span>
            <strong>{{ parcela.nombre_referencial || '—' }}</strong>
          </div>

          <div>
            <span>Productor</span>
            <strong>{{ parcela.productor?.nombre_completo || '—' }}</strong>
          </div>

          <div>
            <span>Superficie</span>
            <strong>{{ parcela.superficie_ha ? parcela.superficie_ha + ' ha' : '—' }}</strong>
          </div>

          <div>
            <span>Tipo de suelo</span>
            <strong>{{ parcela.tipo_suelo || '—' }}</strong>
          </div>

          <div>
            <span>Sistema de riego</span>
            <strong>{{ parcela.sistema_riego || '—' }}</strong>
          </div>

          <div>
            <span>Altitud</span>
            <strong>{{ parcela.altitud_msnm ? parcela.altitud_msnm + ' msnm' : '—' }}</strong>
          </div>

          <div>
            <span>Coordenadas</span>
            <strong>
              {{
                parcela.latitud && parcela.longitud
                  ? parcela.latitud + ', ' + parcela.longitud
                  : parcela.coordenadas_texto || '—'
              }}
            </strong>
          </div>
        </div>
      </section>

      <section class="hf-print-block">
        <h2>Línea de tiempo fitosanitaria</h2>

        <div v-if="!timeline.length" class="hf-print-empty">
          No existe actividad fitosanitaria registrada para esta gestión y campaña.
        </div>

        <div v-else class="hf-print-timeline">
          <article
            v-for="item in timeline"
            :key="item.key"
            class="hf-print-event"
          >
            <div class="hf-print-event-date">
              <strong>{{ diaFecha(item.date) }}</strong>
              <span>{{ mesFecha(item.date) }}</span>
            </div>

            <div class="hf-print-event-body">
              <span>{{ item.label }}</span>
              <h3>{{ item.title }}</h3>
              <p>{{ item.subtitle }}</p>

              <div>
                <b>Resultado:</b> {{ item.result }}
              </div>

              <div>
                <b>Recomendación:</b> {{ item.recommendation }}
              </div>
            </div>

            <div class="hf-print-event-risk">
              {{ item.risk }}
            </div>
          </article>
        </div>
      </section>
    </section>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { urlArchivo } from '@/services/api';
import parcelaService from '@/services/modules/parcelaService';
import '@/styles/historial-fitosanitario.css';
import { normalizeList } from '@/utils/datos';

const parcelas = ref([]);
const historial = ref(null);

const loadingParcelas = ref(true);
const loadingHistorial = ref(false);

const pageError = ref('');
const search = ref('');
const filtroEstado = ref('');
const selectedParcelaId = ref('');
const mostrarDetalle = ref(false);

const selectedAnio = ref(new Date().getFullYear());
const selectedCampania = ref('todas');

const aniosDisponibles = computed(() => {
  const actual = new Date().getFullYear();
  const anios = [];

  for (let i = 0; i <= 7; i += 1) {
    anios.push(actual - i);
  }

  return anios;
});

const nombreCampania = computed(() => {
  if (selectedCampania.value === 'primera') {
    return 'Primera campaña';
  }

  if (selectedCampania.value === 'segunda') {
    return 'Segunda campaña';
  }

  return 'Todas las campañas';
});

const filteredParcelas = computed(() => {
  const q = search.value.trim().toLowerCase();

  return parcelas.value.filter((p) => {
    const texto = [
      p.codigo_parcela,
      p.nombre_referencial,
      p.estado,
      p.tipo_suelo,
      p.sistema_riego,
      nombreProductor(p.productor),
      p.productor?.carnet_identidad,
    ]
      .filter(Boolean)
      .join(' ')
      .toLowerCase();

    const matchSearch = !q || texto.includes(q);
    const matchEstado = !filtroEstado.value || p.estado === filtroEstado.value;

    return matchSearch && matchEstado;
  });
});

const parcela = computed(() => historial.value?.parcela || {});
const resumen = computed(() => historial.value?.resumen || {});
const cultivos = computed(() => historial.value?.cultivos || []);
const inspecciones = computed(() => historial.value?.inspecciones || []);
const diagnosticos = computed(() => historial.value?.diagnosticos || []);
const alertas = computed(() => historial.value?.alertas || []);
const cosechas = computed(() => historial.value?.cosechas || []);

const timeline = computed(() => {
  const items = [];

  inspecciones.value.forEach((i) => {
    const afectacion = Number(i.pct_plantas_afectadas || 0);

    items.push({
      key: `insp-${i.id}`,
      type: 'inspeccion',
      icon: 'ti ti-clipboard-check',
      label: 'Inspección técnica',
      date: i.fecha_visita,
      title: 'Visita de campo registrada',
      subtitle: i.usuario?.nombre || i.usuario?.name || 'Técnico no registrado',
      result: `Afectación observada: ${i.pct_plantas_afectadas ?? '—'} % · Clima: ${i.condicion_climatica || '—'}`,
      risk: afectacion >= 40 ? 'Riesgo alto' : afectacion >= 20 ? 'Riesgo medio' : 'Seguimiento normal',
      recommendation: recortarTexto(i.observaciones || 'Sin observaciones registradas.', 140),
      chips: [
        `Temp. ${i.temperatura_c ?? '—'} °C`,
        `Humedad ${i.humedad_relativa_pct ?? '—'} %`,
        `${i.total_diagnosticos || 0} diagnóstico(s)`,
      ],
    });
  });

  diagnosticos.value.forEach((d) => {
    const enfermedad = d.enfermedad?.nombre || d.enfermedad?.nombre_comun || 'Sin enfermedad asignada';

    items.push({
      key: `diag-${d.id}`,
      type: 'diagnostico',
      icon: 'ti ti-microscope',
      label: 'Diagnóstico CNN',
      date: d.fecha_inspeccion,
      title: enfermedad,
      subtitle: `${Number(d.porcentaje_confianza || 0).toFixed(1)} % de confianza`,
      result: `Severidad: ${limpiarEstado(d.nivel_severidad)} · Validación: ${limpiarEstado(d.estado_validacion)}`,
      risk: limpiarEstado(d.nivel_severidad || 'Sin riesgo'),
      recommendation: recortarTexto(d.recomendacion || 'Sin recomendación registrada.', 150),
      chips: [
        d.modelo_version || 'Modelo CNN',
        limpiarEstado(d.estado_validacion),
      ],
    });
  });

  alertas.value.forEach((a) => {
    items.push({
      key: `alerta-${a.id}`,
      type: 'alerta',
      icon: 'ti ti-bell-ringing',
      label: 'Alerta fitosanitaria',
      date: a.fecha_emision,
      title: a.titulo || 'Alerta generada',
      subtitle: `Estado: ${limpiarEstado(a.estado)}`,
      result: recortarTexto(a.mensaje || 'Sin mensaje registrado.', 150),
      risk: limpiarEstado(a.nivel_riesgo || 'Sin riesgo'),
      recommendation: recortarTexto(a.accion_tomada || 'Sin acción registrada.', 150),
      chips: [
        `Límite: ${formatDate(a.fecha_limite_accion)}`,
        limpiarEstado(a.estado),
      ],
    });
  });

  cosechas.value.forEach((c) => {
    items.push({
      key: `cosecha-${c.id}`,
      type: 'cosecha',
      icon: 'ti ti-plant-2',
      label: 'Cosecha',
      date: c.fecha_cosecha,
      title: 'Cosecha registrada',
      subtitle: `${Number(c.cantidad_toneladas || 0).toFixed(2)} toneladas`,
      result: `Rendimiento: ${Number(c.rendimiento_t_ha || 0).toFixed(2)} t/ha · Calidad: ${c.calidad || '—'}`,
      risk: 'Producción registrada',
      recommendation: recortarTexto(c.destino || 'Sin destino registrado.', 140),
      chips: [
        formatoBs(c.ingreso_estimado_bs),
        c.calidad || 'Sin calidad',
      ],
    });
  });

  return items.sort((a, b) => fechaTimestamp(b.date) - fechaTimestamp(a.date));
});

const resumenTecnico = computed(() => {
  const enfermedadPrincipal = valorMasFrecuente(
    diagnosticos.value.map((d) => d.enfermedad?.nombre || d.enfermedad?.nombre_comun)
  );

  const riesgoPrincipal = valorMasFrecuente(
    alertas.value.map((a) => a.nivel_riesgo)
  );

  const alertasActivas = alertas.value.filter((a) => a.estado === 'Activa').length;

  const recomendaciones = [
    ...alertas.value.map((a) => a.accion_tomada),
    ...diagnosticos.value.map((d) => d.recomendacion),
    ...inspecciones.value.map((i) => i.observaciones),
  ].filter(Boolean);

  let estadoSeguimiento = 'Sin actividad registrada';

  if (alertasActivas > 0) {
    estadoSeguimiento = 'Con alertas activas';
  } else if (diagnosticos.value.length > 0) {
    estadoSeguimiento = 'Con diagnóstico técnico';
  } else if (inspecciones.value.length > 0) {
    estadoSeguimiento = 'Inspeccionado';
  }

  return {
    actividad: `${cultivos.value.length} cultivo(s), ${inspecciones.value.length} inspección(es), ${diagnosticos.value.length} diagnóstico(s)`,
    enfermedadPrincipal: enfermedadPrincipal || 'Sin enfermedad predominante',
    riesgoPrincipal: limpiarEstado(riesgoPrincipal || 'Sin riesgo registrado'),
    estadoSeguimiento,
    ultimaRecomendacion: recortarTexto(recomendaciones[0] || 'Sin recomendación registrada.', 170),
  };
});

onMounted(fetchParcelas);

async function fetchParcelas() {
  loadingParcelas.value = true;
  pageError.value = '';

  try {
    const { data } = await parcelaService.listar();
    parcelas.value = normalizeList(data);
  } catch (err) {
    console.error(err);
    pageError.value = obtenerMensajeError(err, 'No se pudieron cargar las parcelas.');
    parcelas.value = [];
  } finally {
    loadingParcelas.value = false;
  }
}

async function seleccionarParcela(parcelaItem) {
  if (!parcelaItem?.id) {
    return;
  }

  selectedParcelaId.value = parcelaItem.id;
  mostrarDetalle.value = false;

  await cargarHistorial(parcelaItem.id);
}

async function cargarHistorial(idParcela) {
  loadingHistorial.value = true;
  pageError.value = '';
  historial.value = null;

  try {
    const { data } = await parcelaService.historialFitosanitario(idParcela, {
      anio: selectedAnio.value,
      campania: selectedCampania.value,
    });

    historial.value = data;
  } catch (err) {
    console.error(err);
    pageError.value = obtenerMensajeError(err, 'No se pudo cargar el historial fitosanitario.');
    historial.value = null;
  } finally {
    loadingHistorial.value = false;
  }
}

async function recargarHistorial() {
  if (!selectedParcelaId.value) {
    return;
  }

  mostrarDetalle.value = false;

  await cargarHistorial(selectedParcelaId.value);
}

function obtenerMensajeError(err, fallback) {
  return err?.response?.data?.message ||
    err?.response?.data?.error ||
    fallback;
}

function nombreProductor(productor) {
  if (!productor) {
    return '—';
  }

  if (productor.nombre_completo) {
    return productor.nombre_completo;
  }

  return [
    productor.nombres,
    productor.apellido_paterno,
    productor.apellido_materno,
  ]
    .filter(Boolean)
    .join(' ') || '—';
}

function limpiarEstado(value) {
  if (!value) {
    return '—';
  }

  return String(value).replaceAll('_', ' ');
}

function formatDate(dateStr) {
  if (!dateStr) {
    return '—';
  }

  const date = new Date(dateStr);

  if (Number.isNaN(date.getTime())) {
    return '—';
  }

  return date.toLocaleDateString('es-BO', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  });
}

function fechaTimestamp(dateStr) {
  if (!dateStr) {
    return 0;
  }

  const date = new Date(dateStr);

  if (Number.isNaN(date.getTime())) {
    return 0;
  }

  return date.getTime();
}

function diaFecha(dateStr) {
  if (!dateStr) {
    return '—';
  }

  const date = new Date(dateStr);

  if (Number.isNaN(date.getTime())) {
    return '—';
  }

  return date.toLocaleDateString('es-BO', {
    day: '2-digit',
  });
}

function mesFecha(dateStr) {
  if (!dateStr) {
    return 'Sin fecha';
  }

  const date = new Date(dateStr);

  if (Number.isNaN(date.getTime())) {
    return 'Sin fecha';
  }

  return date.toLocaleDateString('es-BO', {
    month: 'short',
    year: 'numeric',
  });
}

function formatoBs(value) {
  return new Intl.NumberFormat('es-BO', {
    style: 'currency',
    currency: 'BOB',
    minimumFractionDigits: 2,
  }).format(Number(value || 0));
}

function recortarTexto(value, max = 120) {
  const texto = String(value || '').trim();

  if (!texto) {
    return '—';
  }

  if (texto.length <= max) {
    return texto;
  }

  return texto.slice(0, max).trim() + '...';
}

function valorMasFrecuente(valores) {
  const conteo = {};

  valores
    .filter(Boolean)
    .forEach((valor) => {
      const key = String(valor).trim();

      if (!key) {
        return;
      }

      conteo[key] = (conteo[key] || 0) + 1;
    });

  return Object.entries(conteo)
    .sort((a, b) => b[1] - a[1])[0]?.[0] || '';
}

function badgeEstado(estado) {
  return {
    Activa: 'hf-green',
    Activo: 'hf-green',
    Pendiente: 'hf-amber',
    En_proceso: 'hf-amber',
    En_descanso: 'hf-amber',
    Completada: 'hf-green',
    Validada: 'hf-blue',
    Atendida: 'hf-green',
    Inactiva: 'hf-red',
    Inactivo: 'hf-red',
  }[estado] || 'hf-gray';
}

function badgeRiesgo(nivel) {
  const value = String(nivel || '').toLowerCase();

  if (value.includes('crit') || value.includes('alto') || value.includes('severa')) {
    return 'hf-red';
  }

  if (value.includes('medio') || value.includes('moderada')) {
    return 'hf-amber';
  }

  if (value.includes('bajo') || value.includes('normal') || value.includes('registrada')) {
    return 'hf-green';
  }

  return 'hf-gray';
}

function imagenDiagnostico(diagnostico) {
  return urlArchivo(diagnostico?.ruta_fotografia);
}

function imprimirHistorial() {
  mostrarDetalle.value = false;

  setTimeout(() => {
    window.print();
  }, 150);
}

function ocultarImagen(event) {
  event.target.style.display = 'none';
}
</script>