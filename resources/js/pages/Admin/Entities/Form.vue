<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface AliasItem {
    id: number;
    alias: string;
    normalized_alias: string;
    alias_type: string;
}

interface SmartphoneSpec {
    chipset: string | null;
    ram: string | null;
    storage: string | null;
    screen_size_inch: number | null;
    screen_type: string | null;
    rear_camera: string | null;
    front_camera: string | null;
    battery_mah: number | null;
    fast_charging_watt: number | null;
    os: string | null;
    network: string | null;
    release_year: number | null;
}

interface CarSpec {
    body_type: string | null;
    engine_cc: number | null;
    cylinder_count: number | null;
    fuel_type: string | null;
    power_hp: number | null;
    torque_nm: number | null;
    transmission: string | null;
    drivetrain: string | null;
    fuel_tank_liter: number | null;
    seating_capacity: number | null;
    dimensions_mm: string | null;
    release_year: number | null;
}

interface MotorcycleSpec {
    body_type: string | null;
    engine_cc: number | null;
    cooling_system: string | null;
    fuel_type: string | null;
    power_hp: number | null;
    torque_nm: number | null;
    transmission: string | null;
    fuel_tank_liter: number | null;
    weight_kg: number | null;
    braking_system: string | null;
    release_year: number | null;
}

interface PersonProfile {
    birth_date: string | null;
    birth_place: string | null;
    occupation: string | null;
    affiliation: string | null;
    active_since_year: number | null;
    official_website: string | null;
}

interface EntityDetail {
    id: number;
    name: string;
    slug: string;
    type: string;
    description: string | null;
    category_id: number;
    parent_id: number | null;
    status: string;
    searchable: boolean;
    rankable: boolean;
    aliases: AliasItem[];
    smartphone_spec: SmartphoneSpec | null;
    car_spec: CarSpec | null;
    motorcycle_spec: MotorcycleSpec | null;
    person_profile: PersonProfile | null;
}

const props = defineProps<{
    entity: EntityDetail;
    categories: Array<{ id: number; name: string; slug: string }>;
    parent_brands: Array<{ id: number; name: string }>;
}>();

const emptySmartphoneSpec: SmartphoneSpec = {
    chipset: null,
    ram: null,
    storage: null,
    screen_size_inch: null,
    screen_type: null,
    rear_camera: null,
    front_camera: null,
    battery_mah: null,
    fast_charging_watt: null,
    os: null,
    network: null,
    release_year: null,
};

const emptyCarSpec: CarSpec = {
    body_type: null,
    engine_cc: null,
    cylinder_count: null,
    fuel_type: null,
    power_hp: null,
    torque_nm: null,
    transmission: null,
    drivetrain: null,
    fuel_tank_liter: null,
    seating_capacity: null,
    dimensions_mm: null,
    release_year: null,
};

const emptyMotorcycleSpec: MotorcycleSpec = {
    body_type: null,
    engine_cc: null,
    cooling_system: null,
    fuel_type: null,
    power_hp: null,
    torque_nm: null,
    transmission: null,
    fuel_tank_liter: null,
    weight_kg: null,
    braking_system: null,
    release_year: null,
};

const emptyPersonProfile: PersonProfile = {
    birth_date: null,
    birth_place: null,
    occupation: null,
    affiliation: null,
    active_since_year: null,
    official_website: null,
};

const form = useForm({
    name: props.entity.name,
    slug: props.entity.slug,
    type: props.entity.type,
    category_id: props.entity.category_id,
    parent_id: props.entity.parent_id,
    description: props.entity.description || '',
    status: props.entity.status,
    searchable: props.entity.searchable,
    rankable: props.entity.rankable,
    smartphone_spec: { ...emptySmartphoneSpec, ...(props.entity.smartphone_spec ?? {}) },
    car_spec: { ...emptyCarSpec, ...(props.entity.car_spec ?? {}) },
    motorcycle_spec: { ...emptyMotorcycleSpec, ...(props.entity.motorcycle_spec ?? {}) },
    person_profile: { ...emptyPersonProfile, ...(props.entity.person_profile ?? {}) },
});

const selectedCategorySlug = computed(
    () => props.categories.find((c) => c.id === form.category_id)?.slug ?? null
);

// Detail specs are for the concrete product, not the brand — a "Samsung" brand
// entity has no chipset/RAM of its own, only its products (e.g. "Galaxy A57") do.
const isProductType = computed(() => form.type === 'product');

const newAlias = ref('');
const newAliasType = ref('common_variant');

function updateEntity() {
    form.put(`/admin/entities/${props.entity.id}`, {
        preserveScroll: true,
    });
}

function addAlias() {
    if (!newAlias.value.trim()) return;

    router.post(
        `/admin/entities/${props.entity.id}/aliases`,
        {
            alias: newAlias.value.trim(),
            alias_type: newAliasType.value,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                newAlias.value = '';
            },
        }
    );
}

function removeAlias(aliasId: number) {
    router.delete(`/admin/entities/${props.entity.id}/aliases/${aliasId}`, {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head :title="`Edit ${entity.name} - Admin`" />

    <div class="flex h-full flex-1 flex-col gap-6 p-6">
        <div class="flex items-center justify-between">
            <div>
                <Link href="/admin/entities" class="text-xs text-neutral-500 hover:underline dark:text-neutral-400">
                    &larr; Back to Entities
                </Link>
                <h1 class="mt-1 text-2xl font-bold tracking-tight text-neutral-900 dark:text-neutral-100">
                    Edit {{ entity.name }}
                </h1>
            </div>
            <a
                :href="`/e/${entity.slug}`"
                target="_blank"
                class="rounded-lg border border-neutral-300 px-3 py-1.5 text-xs font-medium text-neutral-700 hover:bg-neutral-100 dark:border-neutral-700 dark:text-neutral-300"
            >
                View Public Page &nearr;
            </a>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <!-- Edit Form -->
            <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-800 dark:bg-neutral-900 lg:col-span-2">
                <form @submit.prevent="updateEntity" class="space-y-4">
                    <div>
                        <label class="block text-xs font-medium text-neutral-700 dark:text-neutral-300">Name</label>
                        <input
                            v-model="form.name"
                            type="text"
                            class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-neutral-700 dark:text-neutral-300">Slug</label>
                        <input
                            v-model="form.slug"
                            type="text"
                            class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                        />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-neutral-700 dark:text-neutral-300">Type</label>
                            <select
                                v-model="form.type"
                                class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                            >
                                <option value="brand">Brand</option>
                                <option value="product">Product</option>
                                <option value="service">Service</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-neutral-700 dark:text-neutral-300">Category</label>
                            <select
                                v-model="form.category_id"
                                class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                            >
                                <option v-for="cat in categories" :key="cat.id" :value="cat.id">
                                    {{ cat.name }}
                                </option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-neutral-700 dark:text-neutral-300">Parent Brand</label>
                        <select
                            v-model="form.parent_id"
                            class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                        >
                            <option :value="null">None (Standalone Brand)</option>
                            <option v-for="b in parent_brands" :key="b.id" :value="b.id">
                                {{ b.name }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-neutral-700 dark:text-neutral-300">Description</label>
                        <textarea
                            v-model="form.description"
                            rows="3"
                            class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                        ></textarea>
                    </div>

                    <!-- Smartphone spec fieldset: manually curated reference data only, never
                         derived from sentiment (docs/03, ADR-008 clarification). -->
                    <div v-if="isProductType && selectedCategorySlug === 'smartphone'" class="rounded-lg border border-neutral-200 p-4 dark:border-neutral-700">
                        <h3 class="text-xs font-bold tracking-wide text-neutral-500 uppercase">Spesifikasi Smartphone</h3>
                        <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3">
                            <div>
                                <label class="block text-[10px] font-medium text-neutral-500">Chipset</label>
                                <input v-model="form.smartphone_spec.chipset" type="text" class="mt-1 w-full rounded-lg border border-neutral-300 px-2 py-1.5 text-xs dark:border-neutral-700 dark:bg-neutral-800" />
                            </div>
                            <div>
                                <label class="block text-[10px] font-medium text-neutral-500">RAM</label>
                                <input v-model="form.smartphone_spec.ram" type="text" placeholder="8/12 GB" class="mt-1 w-full rounded-lg border border-neutral-300 px-2 py-1.5 text-xs dark:border-neutral-700 dark:bg-neutral-800" />
                            </div>
                            <div>
                                <label class="block text-[10px] font-medium text-neutral-500">Storage</label>
                                <input v-model="form.smartphone_spec.storage" type="text" placeholder="128/256 GB" class="mt-1 w-full rounded-lg border border-neutral-300 px-2 py-1.5 text-xs dark:border-neutral-700 dark:bg-neutral-800" />
                            </div>
                            <div>
                                <label class="block text-[10px] font-medium text-neutral-500">Layar (inci)</label>
                                <input v-model.number="form.smartphone_spec.screen_size_inch" type="number" step="0.01" class="mt-1 w-full rounded-lg border border-neutral-300 px-2 py-1.5 text-xs dark:border-neutral-700 dark:bg-neutral-800" />
                            </div>
                            <div>
                                <label class="block text-[10px] font-medium text-neutral-500">Tipe Layar</label>
                                <input v-model="form.smartphone_spec.screen_type" type="text" placeholder="AMOLED 120Hz" class="mt-1 w-full rounded-lg border border-neutral-300 px-2 py-1.5 text-xs dark:border-neutral-700 dark:bg-neutral-800" />
                            </div>
                            <div>
                                <label class="block text-[10px] font-medium text-neutral-500">Kamera Belakang</label>
                                <input v-model="form.smartphone_spec.rear_camera" type="text" placeholder="50 MP + 8 MP" class="mt-1 w-full rounded-lg border border-neutral-300 px-2 py-1.5 text-xs dark:border-neutral-700 dark:bg-neutral-800" />
                            </div>
                            <div>
                                <label class="block text-[10px] font-medium text-neutral-500">Kamera Depan</label>
                                <input v-model="form.smartphone_spec.front_camera" type="text" class="mt-1 w-full rounded-lg border border-neutral-300 px-2 py-1.5 text-xs dark:border-neutral-700 dark:bg-neutral-800" />
                            </div>
                            <div>
                                <label class="block text-[10px] font-medium text-neutral-500">Baterai (mAh)</label>
                                <input v-model.number="form.smartphone_spec.battery_mah" type="number" class="mt-1 w-full rounded-lg border border-neutral-300 px-2 py-1.5 text-xs dark:border-neutral-700 dark:bg-neutral-800" />
                            </div>
                            <div>
                                <label class="block text-[10px] font-medium text-neutral-500">Fast Charging (W)</label>
                                <input v-model.number="form.smartphone_spec.fast_charging_watt" type="number" class="mt-1 w-full rounded-lg border border-neutral-300 px-2 py-1.5 text-xs dark:border-neutral-700 dark:bg-neutral-800" />
                            </div>
                            <div>
                                <label class="block text-[10px] font-medium text-neutral-500">OS</label>
                                <input v-model="form.smartphone_spec.os" type="text" placeholder="Android 15" class="mt-1 w-full rounded-lg border border-neutral-300 px-2 py-1.5 text-xs dark:border-neutral-700 dark:bg-neutral-800" />
                            </div>
                            <div>
                                <label class="block text-[10px] font-medium text-neutral-500">Jaringan</label>
                                <input v-model="form.smartphone_spec.network" type="text" placeholder="5G" class="mt-1 w-full rounded-lg border border-neutral-300 px-2 py-1.5 text-xs dark:border-neutral-700 dark:bg-neutral-800" />
                            </div>
                            <div>
                                <label class="block text-[10px] font-medium text-neutral-500">Tahun Rilis</label>
                                <input v-model.number="form.smartphone_spec.release_year" type="number" class="mt-1 w-full rounded-lg border border-neutral-300 px-2 py-1.5 text-xs dark:border-neutral-700 dark:bg-neutral-800" />
                            </div>
                        </div>
                    </div>

                    <!-- Car spec fieldset -->
                    <div v-if="isProductType && selectedCategorySlug === 'mobil'" class="rounded-lg border border-neutral-200 p-4 dark:border-neutral-700">
                        <h3 class="text-xs font-bold tracking-wide text-neutral-500 uppercase">Spesifikasi Mobil</h3>
                        <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3">
                            <div>
                                <label class="block text-[10px] font-medium text-neutral-500">Tipe Bodi</label>
                                <input v-model="form.car_spec.body_type" type="text" placeholder="SUV" class="mt-1 w-full rounded-lg border border-neutral-300 px-2 py-1.5 text-xs dark:border-neutral-700 dark:bg-neutral-800" />
                            </div>
                            <div>
                                <label class="block text-[10px] font-medium text-neutral-500">Kapasitas Mesin (cc)</label>
                                <input v-model.number="form.car_spec.engine_cc" type="number" class="mt-1 w-full rounded-lg border border-neutral-300 px-2 py-1.5 text-xs dark:border-neutral-700 dark:bg-neutral-800" />
                            </div>
                            <div>
                                <label class="block text-[10px] font-medium text-neutral-500">Jumlah Silinder</label>
                                <input v-model.number="form.car_spec.cylinder_count" type="number" class="mt-1 w-full rounded-lg border border-neutral-300 px-2 py-1.5 text-xs dark:border-neutral-700 dark:bg-neutral-800" />
                            </div>
                            <div>
                                <label class="block text-[10px] font-medium text-neutral-500">Bahan Bakar</label>
                                <input v-model="form.car_spec.fuel_type" type="text" placeholder="Bensin" class="mt-1 w-full rounded-lg border border-neutral-300 px-2 py-1.5 text-xs dark:border-neutral-700 dark:bg-neutral-800" />
                            </div>
                            <div>
                                <label class="block text-[10px] font-medium text-neutral-500">Tenaga (hp)</label>
                                <input v-model.number="form.car_spec.power_hp" type="number" step="0.1" class="mt-1 w-full rounded-lg border border-neutral-300 px-2 py-1.5 text-xs dark:border-neutral-700 dark:bg-neutral-800" />
                            </div>
                            <div>
                                <label class="block text-[10px] font-medium text-neutral-500">Torsi (Nm)</label>
                                <input v-model.number="form.car_spec.torque_nm" type="number" step="0.1" class="mt-1 w-full rounded-lg border border-neutral-300 px-2 py-1.5 text-xs dark:border-neutral-700 dark:bg-neutral-800" />
                            </div>
                            <div>
                                <label class="block text-[10px] font-medium text-neutral-500">Transmisi</label>
                                <input v-model="form.car_spec.transmission" type="text" placeholder="AT 6-percepatan" class="mt-1 w-full rounded-lg border border-neutral-300 px-2 py-1.5 text-xs dark:border-neutral-700 dark:bg-neutral-800" />
                            </div>
                            <div>
                                <label class="block text-[10px] font-medium text-neutral-500">Penggerak</label>
                                <input v-model="form.car_spec.drivetrain" type="text" placeholder="FWD" class="mt-1 w-full rounded-lg border border-neutral-300 px-2 py-1.5 text-xs dark:border-neutral-700 dark:bg-neutral-800" />
                            </div>
                            <div>
                                <label class="block text-[10px] font-medium text-neutral-500">Tangki BBM (liter)</label>
                                <input v-model.number="form.car_spec.fuel_tank_liter" type="number" class="mt-1 w-full rounded-lg border border-neutral-300 px-2 py-1.5 text-xs dark:border-neutral-700 dark:bg-neutral-800" />
                            </div>
                            <div>
                                <label class="block text-[10px] font-medium text-neutral-500">Kapasitas Duduk</label>
                                <input v-model.number="form.car_spec.seating_capacity" type="number" class="mt-1 w-full rounded-lg border border-neutral-300 px-2 py-1.5 text-xs dark:border-neutral-700 dark:bg-neutral-800" />
                            </div>
                            <div>
                                <label class="block text-[10px] font-medium text-neutral-500">Dimensi (P x L x T mm)</label>
                                <input v-model="form.car_spec.dimensions_mm" type="text" placeholder="4673 x 1849 x 1756" class="mt-1 w-full rounded-lg border border-neutral-300 px-2 py-1.5 text-xs dark:border-neutral-700 dark:bg-neutral-800" />
                            </div>
                            <div>
                                <label class="block text-[10px] font-medium text-neutral-500">Tahun Rilis</label>
                                <input v-model.number="form.car_spec.release_year" type="number" class="mt-1 w-full rounded-lg border border-neutral-300 px-2 py-1.5 text-xs dark:border-neutral-700 dark:bg-neutral-800" />
                            </div>
                        </div>
                    </div>

                    <!-- Motorcycle spec fieldset -->
                    <div v-if="isProductType && selectedCategorySlug === 'motor'" class="rounded-lg border border-neutral-200 p-4 dark:border-neutral-700">
                        <h3 class="text-xs font-bold tracking-wide text-neutral-500 uppercase">Spesifikasi Motor</h3>
                        <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3">
                            <div>
                                <label class="block text-[10px] font-medium text-neutral-500">Tipe</label>
                                <input v-model="form.motorcycle_spec.body_type" type="text" placeholder="Matic" class="mt-1 w-full rounded-lg border border-neutral-300 px-2 py-1.5 text-xs dark:border-neutral-700 dark:bg-neutral-800" />
                            </div>
                            <div>
                                <label class="block text-[10px] font-medium text-neutral-500">Kapasitas Mesin (cc)</label>
                                <input v-model.number="form.motorcycle_spec.engine_cc" type="number" class="mt-1 w-full rounded-lg border border-neutral-300 px-2 py-1.5 text-xs dark:border-neutral-700 dark:bg-neutral-800" />
                            </div>
                            <div>
                                <label class="block text-[10px] font-medium text-neutral-500">Pendingin</label>
                                <input v-model="form.motorcycle_spec.cooling_system" type="text" placeholder="Liquid-cooled" class="mt-1 w-full rounded-lg border border-neutral-300 px-2 py-1.5 text-xs dark:border-neutral-700 dark:bg-neutral-800" />
                            </div>
                            <div>
                                <label class="block text-[10px] font-medium text-neutral-500">Bahan Bakar</label>
                                <input v-model="form.motorcycle_spec.fuel_type" type="text" placeholder="Bensin" class="mt-1 w-full rounded-lg border border-neutral-300 px-2 py-1.5 text-xs dark:border-neutral-700 dark:bg-neutral-800" />
                            </div>
                            <div>
                                <label class="block text-[10px] font-medium text-neutral-500">Tenaga (hp)</label>
                                <input v-model.number="form.motorcycle_spec.power_hp" type="number" step="0.1" class="mt-1 w-full rounded-lg border border-neutral-300 px-2 py-1.5 text-xs dark:border-neutral-700 dark:bg-neutral-800" />
                            </div>
                            <div>
                                <label class="block text-[10px] font-medium text-neutral-500">Torsi (Nm)</label>
                                <input v-model.number="form.motorcycle_spec.torque_nm" type="number" step="0.1" class="mt-1 w-full rounded-lg border border-neutral-300 px-2 py-1.5 text-xs dark:border-neutral-700 dark:bg-neutral-800" />
                            </div>
                            <div>
                                <label class="block text-[10px] font-medium text-neutral-500">Transmisi</label>
                                <input v-model="form.motorcycle_spec.transmission" type="text" placeholder="Manual 6-percepatan" class="mt-1 w-full rounded-lg border border-neutral-300 px-2 py-1.5 text-xs dark:border-neutral-700 dark:bg-neutral-800" />
                            </div>
                            <div>
                                <label class="block text-[10px] font-medium text-neutral-500">Tangki BBM (liter)</label>
                                <input v-model.number="form.motorcycle_spec.fuel_tank_liter" type="number" step="0.1" class="mt-1 w-full rounded-lg border border-neutral-300 px-2 py-1.5 text-xs dark:border-neutral-700 dark:bg-neutral-800" />
                            </div>
                            <div>
                                <label class="block text-[10px] font-medium text-neutral-500">Berat (kg)</label>
                                <input v-model.number="form.motorcycle_spec.weight_kg" type="number" class="mt-1 w-full rounded-lg border border-neutral-300 px-2 py-1.5 text-xs dark:border-neutral-700 dark:bg-neutral-800" />
                            </div>
                            <div>
                                <label class="block text-[10px] font-medium text-neutral-500">Pengereman</label>
                                <input v-model="form.motorcycle_spec.braking_system" type="text" placeholder="Cakram, ABS" class="mt-1 w-full rounded-lg border border-neutral-300 px-2 py-1.5 text-xs dark:border-neutral-700 dark:bg-neutral-800" />
                            </div>
                            <div>
                                <label class="block text-[10px] font-medium text-neutral-500">Tahun Rilis</label>
                                <input v-model.number="form.motorcycle_spec.release_year" type="number" class="mt-1 w-full rounded-lg border border-neutral-300 px-2 py-1.5 text-xs dark:border-neutral-700 dark:bg-neutral-800" />
                            </div>
                        </div>
                    </div>

                    <!-- Person profile fieldset (Tokoh Publik) -->
                    <div v-if="form.type === 'person'" class="rounded-lg border border-neutral-200 p-4 dark:border-neutral-700">
                        <h3 class="text-xs font-bold tracking-wide text-neutral-500 uppercase">Profil Tokoh</h3>
                        <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3">
                            <div>
                                <label class="block text-[10px] font-medium text-neutral-500">Tanggal Lahir</label>
                                <input v-model="form.person_profile.birth_date" type="date" class="mt-1 w-full rounded-lg border border-neutral-300 px-2 py-1.5 text-xs dark:border-neutral-700 dark:bg-neutral-800" />
                            </div>
                            <div>
                                <label class="block text-[10px] font-medium text-neutral-500">Tempat Lahir</label>
                                <input v-model="form.person_profile.birth_place" type="text" class="mt-1 w-full rounded-lg border border-neutral-300 px-2 py-1.5 text-xs dark:border-neutral-700 dark:bg-neutral-800" />
                            </div>
                            <div>
                                <label class="block text-[10px] font-medium text-neutral-500">Profesi / Jabatan</label>
                                <input v-model="form.person_profile.occupation" type="text" class="mt-1 w-full rounded-lg border border-neutral-300 px-2 py-1.5 text-xs dark:border-neutral-700 dark:bg-neutral-800" />
                            </div>
                            <div>
                                <label class="block text-[10px] font-medium text-neutral-500">Afiliasi (Partai/Klub/Perusahaan)</label>
                                <input v-model="form.person_profile.affiliation" type="text" class="mt-1 w-full rounded-lg border border-neutral-300 px-2 py-1.5 text-xs dark:border-neutral-700 dark:bg-neutral-800" />
                            </div>
                            <div>
                                <label class="block text-[10px] font-medium text-neutral-500">Aktif Sejak (Tahun)</label>
                                <input v-model.number="form.person_profile.active_since_year" type="number" class="mt-1 w-full rounded-lg border border-neutral-300 px-2 py-1.5 text-xs dark:border-neutral-700 dark:bg-neutral-800" />
                            </div>
                            <div>
                                <label class="block text-[10px] font-medium text-neutral-500">Website Resmi</label>
                                <input v-model="form.person_profile.official_website" type="text" placeholder="https://..." class="mt-1 w-full rounded-lg border border-neutral-300 px-2 py-1.5 text-xs dark:border-neutral-700 dark:bg-neutral-800" />
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-6 pt-2">
                        <label class="flex items-center gap-2 text-sm">
                            <input v-model="form.searchable" type="checkbox" class="rounded border-neutral-300" />
                            Searchable
                        </label>
                        <label class="flex items-center gap-2 text-sm">
                            <input v-model="form.rankable" type="checkbox" class="rounded border-neutral-300" />
                            Rankable
                        </label>
                        <label class="flex items-center gap-2 text-sm">
                            <select v-model="form.status" class="rounded border border-neutral-300 px-2 py-1 text-xs">
                                <option value="active">Active</option>
                                <option value="disabled">Disabled</option>
                            </select>
                            Status
                        </label>
                    </div>

                    <div class="pt-4">
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-500 disabled:opacity-50"
                        >
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>

            <!-- Aliases Section -->
            <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
                <h2 class="text-base font-bold text-neutral-900 dark:text-neutral-100">Aliases & Mentions</h2>
                <p class="mt-1 text-xs text-neutral-500">
                    Alternative names matched by crawler and search engine.
                </p>

                <!-- Add alias -->
                <div class="mt-4 space-y-2">
                    <input
                        v-model="newAlias"
                        type="text"
                        placeholder="Add new alias..."
                        class="w-full rounded-lg border border-neutral-300 px-3 py-2 text-xs dark:border-neutral-700 dark:bg-neutral-800"
                        @keyup.enter="addAlias"
                    />
                    <div class="flex items-center gap-2">
                        <select
                            v-model="newAliasType"
                            class="w-full rounded-lg border border-neutral-300 px-2 py-1.5 text-xs dark:border-neutral-700 dark:bg-neutral-800"
                        >
                            <option value="common_variant">Common Variant</option>
                            <option value="abbreviation">Abbreviation</option>
                            <option value="misspelling">Misspelling</option>
                        </select>
                        <button
                            type="button"
                            class="whitespace-nowrap rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-500"
                            @click="addAlias"
                        >
                            + Add
                        </button>
                    </div>
                </div>

                <!-- List of aliases -->
                <div class="mt-6 space-y-2">
                    <div
                        v-for="alias in entity.aliases"
                        :key="alias.id"
                        class="flex items-center justify-between rounded-lg border border-neutral-100 bg-neutral-50 p-2.5 dark:border-neutral-800 dark:bg-neutral-800/50"
                    >
                        <div>
                            <div class="text-xs font-medium text-neutral-800 dark:text-neutral-200">{{ alias.alias }}</div>
                            <div class="text-[10px] text-neutral-400 font-mono">
                                norm: {{ alias.normalized_alias }} • {{ alias.alias_type }}
                            </div>
                        </div>
                        <button
                            type="button"
                            class="text-xs text-rose-500 hover:text-rose-700"
                            title="Remove alias"
                            @click="removeAlias(alias.id)"
                        >
                            &times;
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
