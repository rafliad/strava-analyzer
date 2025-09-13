import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

export default function Show({ auth, activity }) {

    // Helper function untuk memformat waktu dari detik ke Jam:Menit:Detik
    const formatTime = (seconds) => {
        if (!seconds) return '00:00:00';
        const h = Math.floor(seconds / 3600).toString().padStart(2, '0');
        const m = Math.floor((seconds % 3600) / 60).toString().padStart(2, '0');
        const s = (seconds % 60).toString().padStart(2, '0');
        return `${h}h ${m}m ${s}s`;
    };

    const stats = [
        { name: 'Jarak', value: `${(activity.distance / 1000).toFixed(2)} km` },
        { name: 'Waktu Bergerak', value: formatTime(activity.moving_time) },
        { name: 'Waktu Total', value: formatTime(activity.elapsed_time) },
        { name: 'Total Tanjakan', value: `${activity.total_elevation_gain} m` },
        { name: 'Tipe', value: activity.type },
        { name: 'Tanggal', value: new Date(activity.start_date).toLocaleDateString('id-ID', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }) },
    ];

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex items-center justify-between">
                     <h2 className="font-semibold text-xl text-gray-800 leading-tight">
                        Detail Aktivitas
                    </h2>
                     <Link href={route('activities.index')} className="text-sm text-indigo-600 hover:text-indigo-900">
                        &larr; Kembali ke Daftar
                    </Link>
                </div>
            }
        >
            <Head title={activity.name} />

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div className="p-6 border-b border-gray-200">
                            <h3 className="text-2xl font-bold text-gray-900">{activity.name}</h3>
                        </div>
                        <div className="bg-gray-50 px-6 py-5">
                            <dl className="grid grid-cols-1 gap-x-4 gap-y-8 sm:grid-cols-2">
                                {stats.map((stat) => (
                                    <div key={stat.name} className="sm:col-span-1">
                                        <dt className="mt-1 text-lg font-semibold text-gray-900">{stat.name}</dt>
                                        <dd className="text-sm font-medium text-gray-500">{stat.value}</dd>
                                    </div>
                                ))}
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

