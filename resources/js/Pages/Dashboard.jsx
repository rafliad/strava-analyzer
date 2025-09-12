import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Head, Link } from "@inertiajs/react"; // NOTE: Penambahan Link untuk navigasi dan aksi
import { useState, useEffect } from "react"; // NOTE: Penambahan hook React untuk state dan side-effects
import axios from "axios"; // NOTE: Penambahan axios untuk mengambil data statistik

// NOTE: Penambahan helper function untuk memformat jarak dari meter ke kilometer
const formatDistance = (distanceInMeters) => {
    return (distanceInMeters / 1000).toFixed(2);
};

export default function Dashboard({ auth }) { // NOTE: Menambahkan prop 'auth' untuk mengakses data user yang sedang login
    // NOTE: Penambahan state untuk menyimpan statistik, status loading, dan pesan error
    const [stats, setStats] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    // NOTE: Penambahan useEffect untuk mengambil data statistik dari backend saat halaman pertama kali dimuat
    useEffect(() => {
        // Hanya ambil data jika user sudah terhubung ke Strava (memiliki strava_id)
        if (auth.user.strava_id) {
            axios.get(route("strava.dashboardStats"))
                .then(response => {
                    setStats(response.data);
                })
                .catch(err => {
                    console.error("Failed to fetch dashboard stats:", err);
                    setError("Could not load dashboard statistics.");
                })
                .finally(() => {
                    setLoading(false);
                });
        } else {
            // Jika user belum terhubung, langsung set loading ke false
            setLoading(false);
        }
    }, []); // Array dependensi kosong berarti hook ini hanya berjalan sekali

    return (
        <AuthenticatedLayout
            user={auth.user} // NOTE: Meneruskan data user ke layout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Dashboard
                </h2>
            }
        >
            <Head title="Dashboard" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    {/* NOTE: Konten dashboard diubah total dari statis menjadi dinamis berdasarkan status koneksi Strava */}
                    {loading ? (
                        <div className="p-6 bg-white shadow-sm sm:rounded-lg text-center text-gray-500">Loading Dashboard...</div>
                    ) : error ? (
                        <div className="p-6 bg-white shadow-sm sm:rounded-lg text-center text-red-500">{error}</div>
                    ) : stats && stats.is_connected ? (
                        // Tampilan jika pengguna SUDAH terhubung ke Strava
                        <div className="space-y-6">
                            {/* Bagian Statistik Utama */}
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div className="bg-white p-6 shadow-sm sm:rounded-lg">
                                    <h3 className="text-lg font-semibold text-gray-700">Distance This Month</h3>
                                    <p className="text-3xl font-bold mt-2 text-gray-900">{formatDistance(stats.total_distance_month)} km</p>
                                </div>
                                <div className="bg-white p-6 shadow-sm sm:rounded-lg">
                                    <h3 className="text-lg font-semibold text-gray-700">Activities This Month</h3>
                                    <p className="text-3xl font-bold mt-2 text-gray-900">{stats.total_activities_month}</p>
                                </div>
                            </div>

                            {/* Bagian Aktivitas Terbaru */}
                            <div className="bg-white p-6 shadow-sm sm:rounded-lg">
                                <div className="flex justify-between items-center mb-4">
                                    <h3 className="text-lg font-semibold text-gray-700">Recent Activities</h3>
                                    <Link href={route('activities.index')} className="text-sm font-medium text-blue-600 hover:underline">View All</Link>
                                </div>
                                <ul className="divide-y divide-gray-200">
                                    {stats.recent_activities.length > 0 ? stats.recent_activities.map(activity => (
                                        <li key={activity.id} className="py-3">
                                            <h3 className="text-lg font-semibold">{activity.name}</h3>
                                        <p className="text-sm text-gray-600">
                                            <span>🏃‍♂️ {(activity.distance / 1000).toFixed(2)} km</span>
                                            <span className="mx-2">|</span>
                                            <span>🕒 {new Date(activity.start_date).toLocaleDateString()}</span>
                                        </p>
                                        </li>
                                    )) : <p className="text-gray-500">No recent activities found.</p>}
                                </ul>
                            </div>

                             {/* Bagian Tombol Disconnect */}
                            <div className="bg-white p-6 shadow-sm sm:rounded-lg">
                                <Link
                                    href={route("strava.disconnect")}
                                    method="post"
                                    as="button"
                                    className="inline-block px-4 py-2 bg-red-600 text-white font-semibold rounded-lg shadow-md hover:bg-red-800 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2"
                                >
                                    Disconnect from Strava
                                </Link>
                            </div>
                        </div>
                    ) : (
                        // Tampilan jika pengguna BELUM terhubung ke Strava
                        <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                            <div className="p-6 text-gray-900">
                                Connect your Strava account to see your stats.
                            </div>
                            <div className="p-6 border-t border-gray-200">
                                <a
                                    href={route('strava.redirect')} // NOTE: Menggunakan helper `route()` untuk URL yang dinamis
                                    className="inline-block px-4 py-2 bg-orange-500 text-white font-semibold rounded-lg shadow-md hover:bg-orange-700"
                                >
                                    Connect with Strava
                                </a>
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

