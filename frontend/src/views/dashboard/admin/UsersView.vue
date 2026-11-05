<template>
  <div class="users-page fade-in">
    <!-- Contextbar -->
    <div class="cbar">
      <div class="bread">
        <i class="ti ti-home" style="font-size:14px"></i>
        <span>›</span>
        <span>Administración</span>
        <span>›</span>
        <span class="cur">Usuarios</span>
      </div>

      <div class="cbar-right">
        <button class="btn btn-s" type="button" @click="exportUsersExcel">
          <i class="ti ti-file-spreadsheet"></i>
          Excel
        </button>

        <button class="btn btn-s" type="button" @click="exportUsersPdf">
          <i class="ti ti-file-type-pdf"></i>
          PDF
        </button>

        <button class="btn btn-p" type="button" @click="openCreate">
          <i class="ti ti-user-plus"></i>
          Nuevo usuario
        </button>
      </div>
    </div>

    <!-- Header -->
    <div class="pg-header users-header">
      <div>
        <div class="pg-title">Gestión de Usuarios</div>
        <div class="pg-sub">
          Administra usuarios, roles y accesos del sistema PROINPA
        </div>
      </div>

      <button class="btn btn-s" type="button" :disabled="loading" @click="fetchAll">
        <i class="ti ti-refresh"></i>
        {{ loading ? 'Actualizando...' : 'Actualizar' }}
      </button>
    </div>

    <!-- Error -->
    <div v-if="error" class="users-alert-error">
      {{ error }}
    </div>

    <!-- Estadísticas -->
    <div v-if="!loading" class="users-stats mb20">
      <div class="sc">
        <div class="slabel">Total usuarios</div>
        <div class="sval">{{ users.length }}</div>
        <div class="ssub">
          <i class="ti ti-users up"></i>
          registrados
        </div>
      </div>

      <div class="sc blue">
        <div class="slabel">Activos</div>
        <div class="sval">{{ usuariosActivos }}</div>
        <div class="ssub">
          <i class="ti ti-check up"></i>
          con acceso
        </div>
      </div>

      <div class="sc amber">
        <div class="slabel">Con foto facial</div>
        <div class="sval">{{ usuariosConFoto }}</div>
        <div class="ssub">
          <i class="ti ti-face-id"></i>
          biometría activa
        </div>
      </div>

      <div class="sc purple">
        <div class="slabel">Roles activos</div>
        <div class="sval">{{ roles.length }}</div>
        <div class="ssub">
          <i class="ti ti-shield"></i>
          configurados
        </div>
      </div>
    </div>

    <!-- Tabla -->
    <div class="card users-card">
      <div class="users-toolbar">
        <div>
          <h3>Listado de usuarios</h3>
          <p>Filtra por rol, estado o búsqueda general.</p>
        </div>

        <div class="users-filters">
          <select v-model="filtroRol" class="users-select">
            <option value="">Todos los roles</option>
            <option v-for="r in roles" :key="r.id" :value="r.id">
              {{ r.nombre }}
            </option>
          </select>

          <select v-model="filtroEstado" class="users-select">
            <option value="">Todos</option>
            <option value="true">Activos</option>
            <option value="false">Inactivos</option>
          </select>

          <div class="search-box users-search">
            <i class="ti ti-search"></i>
            <input v-model="search" placeholder="Buscar usuario..." />
          </div>
        </div>
      </div>

      <div v-if="loading" class="loading-state">
        <div class="spinner"></div>
        <span>Cargando usuarios...</span>
      </div>

      <div v-else class="users-table-wrap">
        <table class="tbl users-table">
          <thead>
            <tr>
              <th>#</th>
              <th>Usuario</th>
              <th>CI</th>
              <th>Correo</th>
              <th>Celular</th>
              <th>Cargo / Área</th>
              <th>Rol</th>
              <th>Estado</th>
              <th>Acciones</th>
            </tr>
          </thead>

          <tbody>
            <tr v-for="u in filteredUsers" :key="u.id">
              <td class="users-id">
                {{ u.id }}
              </td>

              <td>
                <div class="users-person">
                  <div class="users-avatar">
                    <img v-if="u.face_photo" :src="u.face_photo" alt="foto usuario" />
                    <span v-else>{{ inicial(u) }}</span>
                  </div>

                  <div class="users-person-info">
                    <strong>{{ nombreCompleto(u) }}</strong>
                    <small>{{ u.sexo || 'Sin sexo registrado' }}</small>
                  </div>
                </div>
              </td>

              <td class="users-ci">
                {{ formatCi(u) }}
              </td>

              <td class="users-email">
                {{ u.email }}
              </td>

              <td>
                {{ u.celular || '—' }}
              </td>

              <td>
                <div class="users-job">
                  <strong>{{ u.cargo || '—' }}</strong>
                  <small>{{ u.area || 'Sin área' }}</small>
                </div>
              </td>

              <td>
                <span class="b" :class="badgeRol(u.rol?.nombre)">
                  {{ u.rol?.nombre || '—' }}
                </span>
              </td>

              <td>
                <span class="b" :class="u.activo ? 'bg' : 'bgr'">
                  <i :class="u.activo ? 'ti ti-check' : 'ti ti-x'"></i>
                  {{ u.activo ? 'Activo' : 'Inactivo' }}
                </span>
              </td>

              <td>
                <div class="users-actions">
                  <button
                    class="users-action view"
                    type="button"
                    title="Ver detalle"
                    @click="openView(u)"
                  >
                    <i class="ti ti-eye"></i>
                  </button>

                  <button
                    v-if="puedeModificarUsuario(u)"
                    class="users-action edit"
                    type="button"
                    title="Editar"
                    @click="openEdit(u)"
                  >
                    <i class="ti ti-pencil"></i>
                  </button>

                  <button
                    v-if="u.activo && puedeModificarUsuario(u)"
                    class="users-action delete"
                    type="button"
                    title="Desactivar"
                    @click="openDelete(u)"
                  >
                    <i class="ti ti-user-off"></i>
                  </button>

                  <button
                    v-else-if="!u.activo && puedeModificarUsuario(u)"
                    class="users-action activate"
                    type="button"
                    title="Activar"
                    @click="handleActivate(u)"
                  >
                    <i class="ti ti-user-check"></i>
                  </button>

                  <button
                    v-else
                    class="users-action view"
                    type="button"
                    title="Acción restringida"
                    @click="openAlertModal('Solo un Superadmin puede editar, activar o desactivar usuarios con rol Superadmin.')"
                  >
                    <i class="ti ti-lock"></i>
                  </button>
                </div>
              </td>
            </tr>

            <tr v-if="!filteredUsers.length">
              <td colspan="9" style="text-align:center; padding: 28px;">
                No se encontraron usuarios con los filtros seleccionados.
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <UserViewModal
      v-if="showViewModal"
      :user="selectedUser"
      @close="closeViewModal"
    />

    <UserFormModal
      v-if="showFormModal"
      :mode="modalMode"
      :user="selectedUser"
      :roles="roles"
      :saving="saving"
      @close="closeFormModal"
      @save="handleSave"
    />

    <UserDeleteModal
      v-if="showDeleteModal"
      :user="selectedUser"
      :saving="saving"
      @close="closeDeleteModal"
      @confirm="handleDelete"
    />

    <UserAlertModal
      v-if="showAlertModal"
      title="No permitido"
      :message="alertMessage"
      @close="closeAlertModal"
    />
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import usuarioService from '@/services/modules/usuarioService';
import UserFormModal from '../../../components/users/UserFormModal.vue';
import UserDeleteModal from '../../../components/users/UserDeleteModal.vue';
import UserViewModal from '../../../components/users/UserViewModal.vue';
import UserAlertModal from '../../../components/users/UserAlertModal.vue';
import * as XLSX from 'xlsx';
import { saveAs } from 'file-saver';
import jsPDF from 'jspdf';
import autoTable from 'jspdf-autotable';
import '@/styles/users.css';
import { normalizeList } from '@/utils/datos';

const users = ref([]);
const roles = ref([]);

const loading = ref(true);
const saving = ref(false);
const error = ref('');

const search = ref('');
const filtroRol = ref('');
const filtroEstado = ref('');

const showFormModal = ref(false);
const showDeleteModal = ref(false);
const showViewModal = ref(false);
const showAlertModal = ref(false);

const modalMode = ref('create');
const selectedUser = ref(null);
const alertMessage = ref('');

const currentUser = computed(() => {
  try {
    return JSON.parse(localStorage.getItem('user_data') || '{}');
  } catch {
    return {};
  }
});

const rolActual = computed(() => {
  return (
    currentUser.value?.rol?.nombre ||
    currentUser.value?.role?.nombre ||
    currentUser.value?.rol ||
    currentUser.value?.role ||
    ''
  );
});

const esSuperadminActual = computed(() => rolActual.value === 'Superadmin');

const usuariosActivos = computed(() => {
  return users.value.filter((u) => Boolean(u.activo)).length;
});

const usuariosConFoto = computed(() => {
  return users.value.filter((u) => Boolean(u.face_photo)).length;
});

const filteredUsers = computed(() => {
  const lista = Array.isArray(users.value) ? users.value : [];
  const q = search.value.trim().toLowerCase();

  return lista.filter((u) => {
    const texto = [
      u.name,
      u.paterno,
      u.materno,
      u.ci,
      u.email,
      u.celular,
      u.cargo,
      u.area,
      u.oficina_regional,
      u.sexo,
      u.rol?.nombre,
    ]
      .filter(Boolean)
      .join(' ')
      .toLowerCase();

    const matchSearch = !q || texto.includes(q);
    const matchRol = !filtroRol.value || Number(u.id_rol) === Number(filtroRol.value);

    const matchEstado =
      filtroEstado.value === ''
        ? true
        : Boolean(u.activo) === (filtroEstado.value === 'true');

    return matchSearch && matchRol && matchEstado;
  });
});

onMounted(() => {
  fetchAll();
});

async function fetchAll() {
  loading.value = true;
  error.value = '';

  try {
    await Promise.all([
      fetchUsers(),
      fetchRoles(),
    ]);
  } finally {
    loading.value = false;
  }
}

async function fetchUsers() {
  try {
    const { data } = await usuarioService.listar({ todos: true });

    users.value = normalizeList(data);
  } catch (err) {
    if (
      err.code === 'ERR_CANCELED' ||
      err.name === 'CanceledError' ||
      err.message === 'Request aborted'
    ) {
      return;
    }

    console.error(err);
    error.value = err.response?.data?.message || 'No se pudieron cargar los usuarios.';
    users.value = [];
  }
}

async function fetchRoles() {
  try {
    const { data } = await usuarioService.roles();
    const lista = normalizeList(data);

    roles.value = esSuperadminActual.value
      ? lista
      : lista.filter((rol) => rol.nombre !== 'Superadmin');
  } catch (err) {
    if (
      err.code === 'ERR_CANCELED' ||
      err.name === 'CanceledError' ||
      err.message === 'Request aborted'
    ) {
      return;
    }

    console.error(err);
    error.value = err.response?.data?.message || 'No se pudieron cargar los roles.';
    roles.value = [];
  }
}

function usuarioEsSuperadmin(user) {
  const rol =
    user?.rol?.nombre ||
    user?.role?.nombre ||
    user?.rol ||
    user?.role ||
    '';

  return rol === 'Superadmin';
}

function puedeModificarUsuario(user) {
  if (esSuperadminActual.value) {
    return true;
  }

  return !usuarioEsSuperadmin(user);
}

function openCreate() {
  selectedUser.value = null;
  modalMode.value = 'create';
  showFormModal.value = true;
}

function openEdit(user) {
  if (!puedeModificarUsuario(user)) {
    openAlertModal('Solo un Superadmin puede editar usuarios con rol Superadmin.');
    return;
  }

  selectedUser.value = user;
  modalMode.value = 'edit';
  showFormModal.value = true;
}

function closeFormModal() {
  showFormModal.value = false;
  selectedUser.value = null;
}

function openDelete(user) {
  if (!puedeModificarUsuario(user)) {
    openAlertModal('Solo un Superadmin puede desactivar usuarios con rol Superadmin.');
    return;
  }

  selectedUser.value = user;
  showDeleteModal.value = true;
}

function closeDeleteModal() {
  showDeleteModal.value = false;
  selectedUser.value = null;
}

function openView(user) {
  selectedUser.value = user;
  showViewModal.value = true;
}

function closeViewModal() {
  showViewModal.value = false;
  selectedUser.value = null;
}

function openAlertModal(message) {
  alertMessage.value = message;
  showAlertModal.value = true;
}

function closeAlertModal() {
  showAlertModal.value = false;
  alertMessage.value = '';
}

async function handleSave(payload) {
  saving.value = true;
  error.value = '';

  try {
    if (modalMode.value === 'create') {
      await usuarioService.crear(payload);
    } else {
      await usuarioService.actualizar(selectedUser.value.id, payload);
    }

    await fetchUsers();
    closeFormModal();
  } catch (err) {
    console.error(err);

    const mensaje =
      err.response?.data?.message ||
      err.response?.data?.error ||
      'Error al guardar el usuario.';

    error.value = mensaje;
    openAlertModal(mensaje);
  } finally {
    saving.value = false;
  }
}

async function handleDelete() {
  if (!selectedUser.value?.id) {
    return;
  }

  saving.value = true;
  error.value = '';

  try {
    await usuarioService.desactivar(selectedUser.value.id);
    await fetchUsers();
    closeDeleteModal();
  } catch (err) {
    console.error(err);

    const mensaje =
      err.response?.data?.message ||
      err.response?.data?.error ||
      'Error al desactivar el usuario.';

    error.value = mensaje;
    openAlertModal(mensaje);
    closeDeleteModal();
  } finally {
    saving.value = false;
  }
}

async function handleActivate(user) {
  if (!user?.id) {
    return;
  }

  if (!puedeModificarUsuario(user)) {
    openAlertModal('Solo un Superadmin puede activar usuarios con rol Superadmin.');
    return;
  }

  saving.value = true;
  error.value = '';

  try {
    await usuarioService.activar(user.id);
    await fetchUsers();
  } catch (err) {
    console.error(err);

    const mensaje =
      err.response?.data?.message ||
      err.response?.data?.error ||
      'Error al activar el usuario.';

    error.value = mensaje;
    openAlertModal(mensaje);
  } finally {
    saving.value = false;
  }
}

function badgeRol(nombre) {
  return {
    Superadmin: 'bg',
    Administrador: 'bg',
    Agrónomo: 'bb',
    Técnico: 'ba',
    'Encargado de almacén': 'bb',
    Comercial: 'ba',
    Agricultor: 'bgr',
  }[nombre] || 'bgr';
}

function nombreCompleto(user) {
  return [
    user?.name,
    user?.paterno,
    user?.materno,
  ]
    .filter(Boolean)
    .join(' ') || 'Usuario';
}

function inicial(user) {
  return nombreCompleto(user).charAt(0).toUpperCase();
}

function formatCi(user) {
  if (!user?.ci) {
    return '—';
  }

  return `${user.ci}${user.ci_extension ? ' ' + user.ci_extension : ''}`;
}

function usersForReport() {
  return filteredUsers.value.map((u) => ({
    ID: u.id,
    Usuario: nombreCompleto(u),
    CI: formatCi(u),
    Correo: u.email || '—',
    Celular: u.celular || '—',
    Cargo: u.cargo || '—',
    Área: u.area || '—',
    Rol: u.rol?.nombre || '—',
    Estado: u.activo ? 'Activo' : 'Inactivo',
  }));
}

function getReportMetadata() {
  const user = currentUser.value || {};
  const nombre = [
    user.name,
    user.paterno,
    user.materno,
  ].filter(Boolean).join(' ') || 'Usuario no identificado';

  return {
    titulo: 'Reporte de Usuarios',
    sistema: 'Sistema PROINPA - Gestión Agrícola Inteligente',
    generadoPor: nombre,
    correo: user.email || '—',
    rol: user.rol?.nombre || '—',
    fecha: new Date().toLocaleString('es-BO'),
    total: filteredUsers.value.length,
    filtroRol: filtroRol.value
      ? roles.value.find((r) => Number(r.id) === Number(filtroRol.value))?.nombre || '—'
      : 'Todos los roles',
    filtroEstado: filtroEstado.value === ''
      ? 'Todos'
      : filtroEstado.value === 'true'
        ? 'Activos'
        : 'Inactivos',
    busqueda: search.value || 'Sin búsqueda',
  };
}

function exportUsersPdf() {
  const doc = new jsPDF('landscape');
  const meta = getReportMetadata();

  doc.setFillColor(22, 101, 52);
  doc.rect(0, 0, 297, 26, 'F');

  doc.setTextColor(255, 255, 255);
  doc.setFontSize(16);
  doc.setFont('helvetica', 'bold');
  doc.text(meta.sistema, 14, 10);

  doc.setFontSize(12);
  doc.setFont('helvetica', 'normal');
  doc.text(meta.titulo, 14, 18);

  doc.setTextColor(15, 23, 42);
  doc.setFontSize(10);

  doc.text(`Generado por: ${meta.generadoPor}`, 14, 36);
  doc.text(`Rol: ${meta.rol}`, 14, 42);
  doc.text(`Correo: ${meta.correo}`, 14, 48);

  doc.text(`Fecha y hora: ${meta.fecha}`, 160, 36);
  doc.text(`Total de registros: ${meta.total}`, 160, 42);
  doc.text(`Estado: ${meta.filtroEstado}`, 160, 48);

  doc.text(`Rol filtrado: ${meta.filtroRol}`, 14, 56);
  doc.text(`Búsqueda: ${meta.busqueda}`, 160, 56);

  autoTable(doc, {
    startY: 64,
    head: [[
      'ID',
      'Usuario',
      'CI',
      'Correo',
      'Celular',
      'Cargo',
      'Área',
      'Rol',
      'Estado',
    ]],
    body: usersForReport().map((u) => [
      u.ID,
      u.Usuario,
      u.CI,
      u.Correo,
      u.Celular,
      u.Cargo,
      u.Área,
      u.Rol,
      u.Estado,
    ]),
    theme: 'grid',
    styles: {
      fontSize: 8,
      cellPadding: 2.5,
      textColor: [30, 41, 59],
      lineColor: [203, 213, 225],
      lineWidth: 0.2,
    },
    headStyles: {
      fillColor: [22, 101, 52],
      textColor: [255, 255, 255],
      fontStyle: 'bold',
    },
    alternateRowStyles: {
      fillColor: [248, 250, 252],
    },
    didDrawPage: function () {
      const pageCount = doc.internal.getNumberOfPages();
      const pageSize = doc.internal.pageSize;
      const pageWidth = pageSize.width || pageSize.getWidth();
      const pageHeight = pageSize.height || pageSize.getHeight();

      doc.setFontSize(8);
      doc.setTextColor(100, 116, 139);
      doc.text(
        'Documento generado automáticamente por el Sistema PROINPA',
        14,
        pageHeight - 10
      );
      doc.text(
        `Página ${doc.internal.getCurrentPageInfo().pageNumber} de ${pageCount}`,
        pageWidth - 40,
        pageHeight - 10
      );
    },
  });

  doc.save(`reporte_usuarios_${new Date().toISOString().slice(0, 10)}.pdf`);
}

function exportUsersExcel() {
  const rows = usersForReport();
  const meta = getReportMetadata();

  const metadataRows = [
    ['Sistema', meta.sistema],
    ['Reporte', meta.titulo],
    ['Generado por', meta.generadoPor],
    ['Rol', meta.rol],
    ['Correo', meta.correo],
    ['Fecha y hora', meta.fecha],
    ['Total de registros', meta.total],
    ['Filtro rol', meta.filtroRol],
    ['Filtro estado', meta.filtroEstado],
    ['Búsqueda', meta.busqueda],
    [],
  ];

  const worksheet = XLSX.utils.aoa_to_sheet(metadataRows);
  XLSX.utils.sheet_add_json(worksheet, rows, {
    origin: `A${metadataRows.length + 1}`,
  });

  worksheet['!cols'] = [
    { wch: 8 },
    { wch: 32 },
    { wch: 16 },
    { wch: 32 },
    { wch: 16 },
    { wch: 22 },
    { wch: 22 },
    { wch: 22 },
    { wch: 14 },
  ];

  const workbook = XLSX.utils.book_new();
  XLSX.utils.book_append_sheet(workbook, worksheet, 'Usuarios');

  const buffer = XLSX.write(workbook, {
    bookType: 'xlsx',
    type: 'array',
  });

  const blob = new Blob([buffer], {
    type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
  });

  saveAs(blob, `reporte_usuarios_${new Date().toISOString().slice(0, 10)}.xlsx`);
}
</script>