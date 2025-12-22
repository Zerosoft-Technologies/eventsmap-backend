# Vue Frontend AI Prompt

## Role
You are a senior Vue.js developer specializing in Vue 3 with Composition API, Pinia state management, and integration with RESTful APIs. You're tasked with updating an existing Vue frontend to work with the new Events Map API that includes categories and subcategories.

## Task
Update the existing Vue frontend to integrate with the new Events API endpoints. Focus on:
1. Category/subcategory filtering system
2. Updated event listing with new filters
3. Proper state management with Pinia
4. Reactive filtering and pagination

## API Integration Requirements

### 1. Update API Service
Create/update your API service to handle the new endpoints:

```javascript
// src/services/api.js
import axios from 'axios';

const api = axios.create({
  baseURL: process.env.VUE_APP_API_URL || 'http://localhost:8000/api/v1',
});

export default {
  // NEW: Categories endpoints
  getCategories() {
    return api.get('/categories');
  },
  
  getCategory(slug) {
    return api.get(`/categories/${slug}`);
  },
  
  getSubcategories(categorySlug) {
    return api.get(`/categories/${categorySlug}/subcategories`);
  },
  
  // UPDATED: Events endpoints with new filters
  getEvents(params = {}) {
    // Clean up null/empty values
    const cleanParams = Object.fromEntries(
      Object.entries(params).filter(([_, v]) => v !== null && v !== '')
    );
    return api.get('/events', { params: cleanParams });
  },
  
  getEvent(id) {
    return api.get(`/events/${id}`);
  },
};
```

### 2. Create Pinia Store for Categories
```javascript
// src/stores/categories.js
import { defineStore } from 'pinia';
import api from '@/services/api';

export const useCategoriesStore = defineStore('categories', {
  state: () => ({
    categories: [],
    loading: false,
    error: null,
  }),
  
  getters: {
    getCategoryBySlug: (state) => (slug) => {
      return state.categories.find(c => c.slug === slug);
    },
    
    getSubcategoriesByCategorySlug: (state) => (categorySlug) => {
      const category = state.categories.find(c => c.slug === categorySlug);
      return category ? category.subcategories : [];
    },
    
    categoryOptions: (state) => {
      return state.categories.map(c => ({
        label: c.name,
        value: c.slug,
      }));
    },
  },
  
  actions: {
    async fetchCategories() {
      this.loading = true;
      this.error = null;
      
      try {
        const response = await api.getCategories();
        this.categories = response.data.data;
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to fetch categories';
        console.error('Error fetching categories:', error);
      } finally {
        this.loading = false;
      }
    },
  },
});
```

### 3. Update Events Store
```javascript
// src/stores/events.js
import { defineStore } from 'pinia';
import api from '@/services/api';

export const useEventsStore = defineStore('events', {
  state: () => ({
    events: [],
    currentEvent: null,
    loading: false,
    error: null,
    meta: {
      current_page: 1,
      last_page: 1,
      per_page: 20,
      total: 0,
    },
    filters: {
      search: '',
      category: '',
      subcategory: '',
      lat: null,
      lng: null,
      radius: 10,
      from_date: '',
      to_date: '',
      min_price: null,
      max_price: null,
      live_now: false,
      page: 1,
      per_page: 20,
    },
  }),
  
  getters: {
    hasActiveFilters: (state) => {
      return Object.entries(state.filters).some(([key, value]) => {
        if (key === 'page' || key === 'per_page') return false;
        return value !== null && value !== '' && value !== false;
      });
    },
    
    filteredEventsCount: (state) => state.meta.total,
  },
  
  actions: {
    async fetchEvents(resetPage = false) {
      this.loading = true;
      this.error = null;
      
      if (resetPage) {
        this.filters.page = 1;
      }
      
      try {
        const response = await api.getEvents(this.filters);
        this.events = response.data.data;
        this.meta = response.data.meta;
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to fetch events';
        console.error('Error fetching events:', error);
      } finally {
        this.loading = false;
      }
    },
    
    async fetchEvent(id) {
      this.loading = true;
      this.error = null;
      
      try {
        const response = await api.getEvent(id);
        this.currentEvent = response.data.data;
        return response.data.data;
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to fetch event';
        throw error;
      } finally {
        this.loading = false;
      }
    },
    
    updateFilter(key, value) {
      this.filters[key] = value;
      this.fetchEvents(true);
    },
    
    updateFilters(filters) {
      Object.assign(this.filters, filters);
      this.fetchEvents(true);
    },
    
    resetFilters() {
      this.filters = {
        search: '',
        category: '',
        subcategory: '',
        lat: null,
        lng: null,
        radius: 10,
        from_date: '',
        to_date: '',
        min_price: null,
        max_price: null,
        live_now: false,
        page: 1,
        per_page: 20,
      };
      this.fetchEvents();
    },
    
    setPage(page) {
      this.filters.page = page;
      this.fetchEvents();
    },
    
    setPerPage(perPage) {
      this.filters.per_page = perPage;
      this.filters.page = 1;
      this.fetchEvents();
    },
  },
});
```

### 4. Update Event List Component
```vue
<!-- src/components/EventList.vue -->
<template>
  <div class="event-list">
    <!-- Category Filter -->
    <div class="filter-section">
      <h3>Categories</h3>
      <select 
        v-model="eventsStore.filters.category" 
        @change="onCategoryChange"
      >
        <option value="">All Categories</option>
        <option 
          v-for="category in categoriesStore.categories" 
          :key="category.id"
          :value="category.slug"
        >
          {{ category.name }}
        </option>
      </select>
      
      <!-- Subcategory Filter -->
      <select 
        v-model="eventsStore.filters.subcategory"
        @change="eventsStore.fetchEvents(true)"
        :disabled="!eventsStore.filters.category"
      >
        <option value="">All Subcategories</option>
        <option 
          v-for="subcategory in availableSubcategories"
          :key="subcategory.id"
          :value="subcategory.slug"
        >
          {{ subcategory.name }}
        </option>
      </select>
    </div>
    
    <!-- Search Filter -->
    <div class="filter-section">
      <input
        v-model="searchQuery"
        @input="onSearchInput"
        type="text"
        placeholder="Search events..."
      >
    </div>
    
    <!-- Other Filters -->
    <div class="filter-section">
      <label>
        <input
          type="checkbox"
          v-model="eventsStore.filters.live_now"
          @change="eventsStore.fetchEvents(true)"
        >
        Live Events Only
      </label>
      
      <div class="price-filter">
        <input
          type="number"
          v-model="minPrice"
          @input="onPriceChange"
          placeholder="Min price"
        >
        <input
          type="number"
          v-model="maxPrice"
          @input="onPriceChange"
          placeholder="Max price"
        >
      </div>
    </div>
    
    <!-- Loading State -->
    <div v-if="eventsStore.loading" class="loading">
      Loading events...
    </div>
    
    <!-- Events Grid -->
    <div v-else class="events-grid">
      <EventCard
        v-for="event in eventsStore.events"
        :key="event.id"
        :event="event"
        @click="goToEvent(event.id)"
      />
    </div>
    
    <!-- No Results -->
    <div v-if="!eventsStore.loading && eventsStore.events.length === 0" class="no-results">
      No events found matching your criteria.
    </div>
    
    <!-- Pagination -->
    <Pagination
      v-if="eventsStore.meta.last_page > 1"
      :current-page="eventsStore.meta.current_page"
      :last-page="eventsStore.meta.last_page"
      :total="eventsStore.meta.total"
      @page-change="eventsStore.setPage"
    />
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useEventsStore } from '@/stores/events';
import { useCategoriesStore } from '@/stores/categories';
import { debounce } from 'lodash-es';
import EventCard from './EventCard.vue';
import Pagination from './Pagination.vue';

const router = useRouter();
const eventsStore = useEventsStore();
const categoriesStore = useCategoriesStore();

// Local reactive refs
const searchQuery = ref(eventsStore.filters.search);
const minPrice = ref(eventsStore.filters.min_price);
const maxPrice = ref(eventsStore.filters.max_price);

// Computed
const availableSubcategories = computed(() => {
  if (!eventsStore.filters.category) return [];
  return categoriesStore.getSubcategoriesByCategorySlug(eventsStore.filters.category);
});

// Methods
const onSearchInput = debounce(() => {
  eventsStore.updateFilter('search', searchQuery.value);
}, 300);

const onCategoryChange = () => {
  eventsStore.filters.subcategory = '';
  eventsStore.fetchEvents(true);
};

const onPriceChange = debounce(() => {
  eventsStore.updateFilters({
    min_price: minPrice.value ? parseFloat(minPrice.value) : null,
    max_price: maxPrice.value ? parseFloat(maxPrice.value) : null,
  });
}, 300);

const goToEvent = (id) => {
  router.push(`/events/${id}`);
};

// Lifecycle
onMounted(async () => {
  await categoriesStore.fetchCategories();
  await eventsStore.fetchEvents();
});

// Watch for live events to refresh periodically
let liveEventsInterval = null;
watch(
  () => eventsStore.filters.live_now,
  (isLive) => {
    if (isLive) {
      liveEventsInterval = setInterval(() => {
        eventsStore.fetchEvents();
      }, 60000); // Refresh every minute
    } else {
      if (liveEventsInterval) {
        clearInterval(liveEventsInterval);
        liveEventsInterval = null;
      }
    }
  },
  { immediate: true }
);

// Cleanup
onUnmounted(() => {
  if (liveEventsInterval) {
    clearInterval(liveEventsInterval);
  }
});
</script>

<style scoped>
.event-list {
  padding: 20px;
}

.filter-section {
  margin-bottom: 20px;
  display: flex;
  gap: 10px;
  align-items: center;
}

.filter-section select,
.filter-section input[type="text"] {
  padding: 8px 12px;
  border: 1px solid #ddd;
  border-radius: 4px;
}

.price-filter {
  display: flex;
  gap: 10px;
}

.events-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
  gap: 20px;
}

.loading {
  text-align: center;
  padding: 40px;
  font-size: 18px;
}

.no-results {
  text-align: center;
  padding: 40px;
  color: #666;
}
</style>
```

### 5. Update Event Card Component
```vue
<!-- src/components/EventCard.vue -->
<template>
  <div class="event-card" @click="$emit('click')">
    <!-- Live Badge -->
    <div v-if="event.is_live_now" class="live-badge">
      LIVE NOW
    </div>
    
    <!-- Event Image (if available) -->
    <div v-if="event.image" class="event-image">
      <img :src="event.image" :alt="event.title">
    </div>
    
    <!-- Event Content -->
    <div class="event-content">
      <h3 class="event-title">{{ event.title }}</h3>
      <p class="event-description">{{ event.description }}</p>
      
      <!-- Category & Subcategory -->
      <div class="event-categories">
        <span class="category-badge">{{ getCategoryName(event.category_id) }}</span>
        <span v-if="event.subcategory_id" class="subcategory-badge">
          {{ getSubcategoryName(event.subcategory_id) }}
        </span>
      </div>
      
      <!-- Event Details -->
      <div class="event-details">
        <div class="detail-item">
          <CalendarIcon />
          <span>{{ formatDate(event.start_datetime) }}</span>
        </div>
        <div class="detail-item">
          <MapPinIcon />
          <span>{{ event.city }}</span>
        </div>
        <div v-if="event.distance_km" class="detail-item">
          <NavigationIcon />
          <span>{{ event.distance_km }} km away</span>
        </div>
      </div>
      
      <!-- Price -->
      <div class="event-price">
        <span v-if="event.price" class="price">€{{ event.price }}</span>
        <span v-else class="free">Free</span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { useCategoriesStore } from '@/stores/categories';
import { format } from 'date-fns';
import CalendarIcon from '@/components/icons/CalendarIcon.vue';
import MapPinIcon from '@/components/icons/MapPinIcon.vue';
import NavigationIcon from '@/components/icons/NavigationIcon.vue';

const props = defineProps({
  event: {
    type: Object,
    required: true,
  },
});

defineEmits(['click']);

const categoriesStore = useCategoriesStore();

const getCategoryName = (categoryId) => {
  const category = categoriesStore.categories.find(c => c.id === categoryId);
  return category ? category.name : '';
};

const getSubcategoryName = (subcategoryId) => {
  for (const category of categoriesStore.categories) {
    const subcategory = category.subcategories.find(s => s.id === subcategoryId);
    if (subcategory) return subcategory.name;
  }
  return '';
};

const formatDate = (dateString) => {
  return format(new Date(dateString), 'MMM d, yyyy • h:mm a');
};
</script>

<style scoped>
.event-card {
  border: 1px solid #e0e0e0;
  border-radius: 8px;
  overflow: hidden;
  cursor: pointer;
  transition: transform 0.2s, box-shadow 0.2s;
}

.event-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.live-badge {
  position: absolute;
  top: 10px;
  right: 10px;
  background: #ff4444;
  color: white;
  padding: 4px 8px;
  border-radius: 4px;
  font-size: 12px;
  font-weight: bold;
  z-index: 1;
}

.event-image {
  height: 200px;
  overflow: hidden;
}

.event-image img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.event-content {
  padding: 16px;
}

.event-title {
  font-size: 18px;
  font-weight: 600;
  margin-bottom: 8px;
}

.event-description {
  color: #666;
  margin-bottom: 12px;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.event-categories {
  display: flex;
  gap: 8px;
  margin-bottom: 12px;
}

.category-badge,
.subcategory-badge {
  padding: 4px 8px;
  border-radius: 4px;
  font-size: 12px;
}

.category-badge {
  background: #e3f2fd;
  color: #1976d2;
}

.subcategory-badge {
  background: #f3e5f5;
  color: #7b1fa2;
}

.event-details {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin-bottom: 12px;
}

.detail-item {
  display: flex;
  align-items: center;
  gap: 6px;
  color: #666;
  font-size: 14px;
}

.detail-item svg {
  width: 16px;
  height: 16px;
}

.event-price {
  font-size: 18px;
  font-weight: 600;
}

.price {
  color: #4caf50;
}

.free {
  color: #2196f3;
}
</style>
```

## Implementation Checklist

1. ✅ Update API service with new endpoints
2. ✅ Create categories Pinia store
3. ✅ Update events Pinia store with new filters
4. ✅ Add category/subcategory filters to UI
5. ✅ Update event cards to show categories
6. ✅ Implement debounced search
7. ✅ Add live events auto-refresh
8. ✅ Update pagination to work with new meta structure

## Important Notes

1. **Use slugs for category/subcategory filtering**, not IDs
2. **Live events refresh every 60 seconds** when filter is active
3. **Distance (km) only appears with geo filters**
4. **Clear subcategory when category changes**
5. **Debounce search and price filters** (300ms)
6. **Handle loading states properly**
7. **Cache categories for 24 hours** if needed

## Testing

Test these scenarios:
- Category selection updates subcategories
- Search works with category filters
- Live events refresh automatically
- Pagination maintains filters
- Price filters work with decimals
- Clear filters resets everything
