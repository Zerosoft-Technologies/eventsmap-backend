# Super-Admin Panel Blueprint
## Part 6: CoreUI React Implementation Strategy

---

# 9. COREUI REACT IMPLEMENTATION STRATEGY

## 9.1 Sidebar Navigation Structure

```javascript
// Proposed _nav.js structure
const adminNav = [
  {
    component: CNavItem,
    name: 'Dashboard',
    to: '/dashboard',
    icon: <CIcon icon={cilSpeedometer} />
  },
  {
    component: CNavTitle,
    name: 'Content Management'
  },
  {
    component: CNavGroup,
    name: 'Events',
    icon: <CIcon icon={cilCalendar} />,
    items: [
      { name: 'All Events', to: '/events' },
      { name: 'Create Event', to: '/events/create' },
      { name: 'Drafts', to: '/events?status=draft' },
      { name: 'Published', to: '/events?status=published' },
      { name: 'Featured', to: '/events?status=featured' },
      { name: 'Archived', to: '/events?status=archived' }
    ]
  },
  {
    component: CNavItem,
    name: 'Talents',
    to: '/talents',
    icon: <CIcon icon={cilPeople} />
  },
  {
    component: CNavItem,
    name: 'Categories',
    to: '/categories',
    icon: <CIcon icon={cilTags} />
  },
  {
    component: CNavItem,
    name: 'Media Library',
    to: '/media',
    icon: <CIcon icon={cilImage} />
  },
  {
    component: CNavTitle,
    name: 'Analytics'
  },
  {
    component: CNavItem,
    name: 'Reports',
    to: '/analytics',
    icon: <CIcon icon={cilChartPie} />
  },
  {
    component: CNavTitle,
    name: 'Administration'
  },
  {
    component: CNavItem,
    name: 'Admin Users',
    to: '/admin-users',
    icon: <CIcon icon={cilUser} />
  },
  {
    component: CNavItem,
    name: 'Settings',
    to: '/settings',
    icon: <CIcon icon={cilSettings} />
  },
  {
    component: CNavItem,
    name: 'Audit Logs',
    to: '/audit-logs',
    icon: <CIcon icon={cilHistory} />
  }
];
```

---

## 9.2 Page Routing

```javascript
// Proposed routes.js additions
const adminRoutes = [
  // Dashboard
  { path: '/dashboard', name: 'Dashboard', element: Dashboard },
  
  // Events
  { path: '/events', name: 'Events', element: EventList },
  { path: '/events/create', name: 'Create Event', element: EventForm },
  { path: '/events/:id', name: 'View Event', element: EventDetail },
  { path: '/events/:id/edit', name: 'Edit Event', element: EventForm },
  
  // Talents
  { path: '/talents', name: 'Talents', element: TalentList },
  { path: '/talents/create', name: 'Create Talent', element: TalentForm },
  { path: '/talents/:id', name: 'View Talent', element: TalentDetail },
  { path: '/talents/:id/edit', name: 'Edit Talent', element: TalentForm },
  
  // Categories
  { path: '/categories', name: 'Categories', element: CategoryList },
  { path: '/categories/create', name: 'Create Category', element: CategoryForm },
  { path: '/categories/:id/edit', name: 'Edit Category', element: CategoryForm },
  
  // Media
  { path: '/media', name: 'Media Library', element: MediaLibrary },
  
  // Analytics
  { path: '/analytics', name: 'Analytics', element: Analytics },
  
  // Admin Users
  { path: '/admin-users', name: 'Admin Users', element: AdminUserList },
  { path: '/admin-users/create', name: 'Create Admin', element: AdminUserForm },
  { path: '/admin-users/:id/edit', name: 'Edit Admin', element: AdminUserForm },
  
  // Settings
  { path: '/settings', name: 'Settings', element: Settings },
  
  // Audit Logs
  { path: '/audit-logs', name: 'Audit Logs', element: AuditLogs },
  
  // Auth (outside main layout)
  { path: '/login', name: 'Login', element: Login },
  { path: '/forgot-password', name: 'Forgot Password', element: ForgotPassword },
  { path: '/reset-password', name: 'Reset Password', element: ResetPassword }
];
```

---

## 9.3 Directory Structure

```
src/
├── components/
│   ├── common/
│   │   ├── DataTable/
│   │   │   ├── DataTable.js
│   │   │   ├── TableFilters.js
│   │   │   ├── TablePagination.js
│   │   │   └── BulkActions.js
│   │   ├── Forms/
│   │   │   ├── FormField.js
│   │   │   ├── RichTextEditor.js
│   │   │   ├── ImageUploader.js
│   │   │   ├── DateTimePicker.js
│   │   │   ├── LocationPicker.js
│   │   │   ├── TagInput.js
│   │   │   └── RepeatableFields.js
│   │   ├── Modals/
│   │   │   ├── ConfirmModal.js
│   │   │   ├── MediaPickerModal.js
│   │   │   └── TalentPickerModal.js
│   │   ├── Feedback/
│   │   │   ├── LoadingSpinner.js
│   │   │   ├── EmptyState.js
│   │   │   └── ErrorBoundary.js
│   │   └── Layout/
│   │       ├── PageHeader.js
│   │       └── Tabs.js
│   │
│   └── domain/
│       ├── events/
│       │   ├── EventStatusBadge.js
│       │   ├── EventCard.js
│       │   └── EventTabs/
│       │       ├── OverviewTab.js
│       │       ├── AboutTab.js
│       │       ├── DateLocationTab.js
│       │       ├── TalentsTab.js
│       │       ├── MediaTab.js
│       │       ├── BookingTab.js
│       │       ├── SeoTab.js
│       │       └── SettingsTab.js
│       ├── talents/
│       │   ├── TalentCard.js
│       │   └── TalentSelector.js
│       ├── categories/
│       │   └── CategoryTree.js
│       └── media/
│           ├── MediaGrid.js
│           └── MediaUploader.js
│
├── views/
│   ├── dashboard/
│   │   └── Dashboard.js
│   ├── events/
│   │   ├── EventList.js
│   │   ├── EventDetail.js
│   │   └── EventForm.js
│   ├── talents/
│   │   ├── TalentList.js
│   │   ├── TalentDetail.js
│   │   └── TalentForm.js
│   ├── categories/
│   │   ├── CategoryList.js
│   │   └── CategoryForm.js
│   ├── media/
│   │   └── MediaLibrary.js
│   ├── analytics/
│   │   └── Analytics.js
│   ├── admin-users/
│   │   ├── AdminUserList.js
│   │   └── AdminUserForm.js
│   ├── settings/
│   │   └── Settings.js
│   ├── audit-logs/
│   │   └── AuditLogs.js
│   └── auth/
│       ├── Login.js
│       ├── ForgotPassword.js
│       └── ResetPassword.js
│
├── services/
│   ├── api/
│   │   ├── apiClient.js
│   │   ├── authService.js
│   │   ├── eventService.js
│   │   ├── talentService.js
│   │   ├── categoryService.js
│   │   ├── mediaService.js
│   │   ├── userService.js
│   │   ├── settingsService.js
│   │   └── analyticsService.js
│   └── utils/
│       ├── validation.js
│       ├── formatters.js
│       └── helpers.js
│
├── hooks/
│   ├── useAuth.js
│   ├── useApi.js
│   ├── usePagination.js
│   ├── useDebounce.js
│   └── useToast.js
│
├── store/
│   ├── index.js
│   ├── authSlice.js
│   └── uiSlice.js
│
└── context/
    ├── AuthContext.js
    └── ToastContext.js
```

---

## 9.4 Service Layer Pattern

```javascript
// services/api/apiClient.js
import axios from 'axios';

const apiClient = axios.create({
  baseURL: import.meta.env.VITE_API_URL + '/api/admin',
  withCredentials: true,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json'
  }
});

// Request interceptor
apiClient.interceptors.request.use((config) => {
  config.headers['X-Request-ID'] = generateUUID();
  return config;
});

// Response interceptor
apiClient.interceptors.response.use(
  (response) => response.data,
  (error) => {
    if (error.response?.status === 401) {
      // Redirect to login
    }
    return Promise.reject(normalizeError(error));
  }
);

export default apiClient;
```

```javascript
// services/api/eventService.js
import apiClient from './apiClient';

export const eventService = {
  // List
  getAll: (params) => apiClient.get('/events', { params }),
  getById: (id) => apiClient.get(`/events/${id}`),
  
  // CRUD
  create: (data) => apiClient.post('/events', data),
  update: (id, data) => apiClient.put(`/events/${id}`, data),
  patch: (id, data) => apiClient.patch(`/events/${id}`, data),
  delete: (id) => apiClient.delete(`/events/${id}`),
  restore: (id) => apiClient.post(`/events/${id}/restore`),
  duplicate: (id) => apiClient.post(`/events/${id}/duplicate`),
  
  // Status
  publish: (id) => apiClient.post(`/events/${id}/publish`),
  unpublish: (id) => apiClient.post(`/events/${id}/unpublish`),
  feature: (id) => apiClient.post(`/events/${id}/feature`),
  unfeature: (id) => apiClient.post(`/events/${id}/unfeature`),
  cancel: (id) => apiClient.post(`/events/${id}/cancel`),
  archive: (id) => apiClient.post(`/events/${id}/archive`),
  
  // Relations
  getTalents: (id) => apiClient.get(`/events/${id}/talents`),
  attachTalents: (id, data) => apiClient.post(`/events/${id}/talents`, data),
  detachTalent: (id, talentId) => apiClient.delete(`/events/${id}/talents/${talentId}`),
  reorderTalents: (id, data) => apiClient.patch(`/events/${id}/talents/reorder`, data),
  
  // Bulk
  bulkDelete: (ids) => apiClient.post('/events/bulk/delete', { ids }),
  bulkPublish: (ids) => apiClient.post('/events/bulk/publish', { ids }),
  bulkArchive: (ids) => apiClient.post('/events/bulk/archive', { ids }),
};
```

---

## 9.5 State Management

**Recommended:** React Query for server state + Redux Toolkit for UI state

```javascript
// hooks/useEvents.js (React Query pattern)
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { eventService } from '../services/api/eventService';

export function useEvents(params) {
  return useQuery({
    queryKey: ['events', params],
    queryFn: () => eventService.getAll(params),
  });
}

export function useEvent(id) {
  return useQuery({
    queryKey: ['events', id],
    queryFn: () => eventService.getById(id),
    enabled: !!id,
  });
}

export function useCreateEvent() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: eventService.create,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['events'] });
    },
  });
}

export function useUpdateEvent() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ id, data }) => eventService.update(id, data),
    onSuccess: (_, { id }) => {
      queryClient.invalidateQueries({ queryKey: ['events'] });
      queryClient.invalidateQueries({ queryKey: ['events', id] });
    },
  });
}
```

```javascript
// store/uiSlice.js (Redux for UI state)
import { createSlice } from '@reduxjs/toolkit';

const uiSlice = createSlice({
  name: 'ui',
  initialState: {
    sidebarShow: true,
    sidebarUnfoldable: false,
    theme: 'light',
  },
  reducers: {
    toggleSidebar: (state) => {
      state.sidebarShow = !state.sidebarShow;
    },
    setTheme: (state, action) => {
      state.theme = action.payload;
    },
  },
});

export const { toggleSidebar, setTheme } = uiSlice.actions;
export default uiSlice.reducer;
```

---

## 9.6 UI Patterns

### Table Layout Pattern

```javascript
// views/events/EventList.js structure
<CCard>
  <CCardHeader>
    <PageHeader 
      title="Events" 
      actions={<CButton to="/events/create">Create Event</CButton>}
    />
  </CCardHeader>
  <CCardBody>
    <TableFilters 
      filters={filters}
      onChange={setFilters}
    />
    <DataTable
      columns={columns}
      data={events}
      loading={isLoading}
      selectable
      onSelectionChange={setSelected}
    />
    <BulkActions 
      selected={selected}
      actions={bulkActions}
    />
    <TablePagination 
      pagination={pagination}
      onChange={setPage}
    />
  </CCardBody>
</CCard>
```

### Form Pattern (Tabbed)

```javascript
// views/events/EventForm.js structure
<CCard>
  <CCardHeader>
    <PageHeader title={isEdit ? 'Edit Event' : 'Create Event'} />
  </CCardHeader>
  <CCardBody>
    <CNav variant="tabs">
      <CNavItem><CNavLink active={tab === 0}>Overview</CNavLink></CNavItem>
      <CNavItem><CNavLink active={tab === 1}>About</CNavLink></CNavItem>
      <CNavItem><CNavLink active={tab === 2}>Date & Location</CNavLink></CNavItem>
      <CNavItem><CNavLink active={tab === 3}>Talents</CNavLink></CNavItem>
      <CNavItem><CNavLink active={tab === 4}>Media</CNavLink></CNavItem>
      <CNavItem><CNavLink active={tab === 5}>Booking</CNavLink></CNavItem>
      <CNavItem><CNavLink active={tab === 6}>SEO</CNavLink></CNavItem>
      <CNavItem><CNavLink active={tab === 7}>Settings</CNavLink></CNavItem>
    </CNav>
    <CTabContent>
      <CTabPane visible={tab === 0}><OverviewTab form={form} /></CTabPane>
      <CTabPane visible={tab === 1}><AboutTab form={form} /></CTabPane>
      {/* ... other tabs */}
    </CTabContent>
  </CCardBody>
  <CCardFooter>
    <CButton color="secondary" onClick={onCancel}>Cancel</CButton>
    <CButton color="primary" onClick={onSave} disabled={saving}>
      {saving ? <CSpinner size="sm" /> : 'Save'}
    </CButton>
  </CCardFooter>
</CCard>
```

### Modal Usage

```javascript
// Confirmation Modal
<ConfirmModal
  visible={showDeleteModal}
  title="Delete Event"
  message="Are you sure you want to delete this event? This action cannot be undone."
  confirmText="Delete"
  confirmColor="danger"
  onConfirm={handleDelete}
  onCancel={() => setShowDeleteModal(false)}
/>

// Media Picker Modal
<MediaPickerModal
  visible={showMediaPicker}
  multiple={true}
  selected={selectedMedia}
  onSelect={handleMediaSelect}
  onClose={() => setShowMediaPicker(false)}
/>
```

### Toast Notifications

```javascript
// hooks/useToast.js usage
const { addToast } = useToast();

// Success
addToast({
  title: 'Success',
  message: 'Event published successfully',
  color: 'success',
  autohide: true,
  delay: 5000
});

// Error
addToast({
  title: 'Error',
  message: error.message,
  color: 'danger',
  autohide: false
});
```

---

## 9.7 Error & Loading Handling

```javascript
// components/common/Feedback/LoadingSpinner.js
export const LoadingSpinner = ({ fullPage = false }) => (
  <div className={fullPage ? 'loading-overlay' : 'loading-inline'}>
    <CSpinner color="primary" />
  </div>
);

// components/common/Feedback/EmptyState.js
export const EmptyState = ({ icon, title, description, action }) => (
  <div className="empty-state text-center py-5">
    <CIcon icon={icon} size="3xl" className="text-muted mb-3" />
    <h4>{title}</h4>
    <p className="text-muted">{description}</p>
    {action}
  </div>
);

// components/common/Feedback/ErrorBoundary.js
export class ErrorBoundary extends Component {
  state = { hasError: false, error: null };
  
  static getDerivedStateFromError(error) {
    return { hasError: true, error };
  }
  
  render() {
    if (this.state.hasError) {
      return <ErrorFallback error={this.state.error} />;
    }
    return this.props.children;
  }
}
```

---

## 9.8 CoreUI Page Hierarchy

```
/                           → Redirect to /dashboard
├── /login                  → Login (public)
├── /forgot-password        → Forgot Password (public)
├── /reset-password         → Reset Password (public)
│
└── [Authenticated Layout]
    ├── /dashboard          → Dashboard
    │
    ├── /events             → Event List
    │   ├── /create         → Create Event
    │   ├── /:id            → Event Detail (read-only view)
    │   └── /:id/edit       → Edit Event (tabbed form)
    │
    ├── /talents            → Talent List
    │   ├── /create         → Create Talent
    │   ├── /:id            → Talent Detail
    │   └── /:id/edit       → Edit Talent
    │
    ├── /categories         → Category List (tree view)
    │   ├── /create         → Create Category
    │   └── /:id/edit       → Edit Category
    │
    ├── /media              → Media Library (grid view)
    │
    ├── /analytics          → Analytics Dashboard
    │
    ├── /admin-users        → Admin User List
    │   ├── /create         → Create Admin
    │   └── /:id/edit       → Edit Admin
    │
    ├── /settings           → Settings (grouped)
    │
    └── /audit-logs         → Audit Log List
```

---

## 9.9 Recommended Dependencies

```json
{
  "dependencies": {
    "@coreui/coreui": "^5.x",
    "@coreui/react": "^5.x",
    "@coreui/icons": "^3.x",
    "@coreui/icons-react": "^2.x",
    "@tanstack/react-query": "^5.x",
    "@reduxjs/toolkit": "^2.x",
    "react-redux": "^9.x",
    "react-router-dom": "^6.x",
    "react-hook-form": "^7.x",
    "axios": "^1.x",
    "date-fns": "^3.x",
    "react-quill": "^2.x",
    "react-dropzone": "^14.x",
    "react-beautiful-dnd": "^13.x",
    "@react-google-maps/api": "^2.x"
  }
}
```
