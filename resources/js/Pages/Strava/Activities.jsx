// resources/js/Pages/Strava/Activities.jsx

import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Head } from "@inertiajs/react";
import { useEffect, useState } from "react";
import axios from "axios"; // Kita akan menggunakan axios untuk fetch data

export default function Activities({ auth }) {
    // State untuk menyimpan daftar aktivitas
    const [activities, setActivities] = useState([]);
    // State untuk menandakan proses loading
    const [loading, setLoading] = useState(true);
    // State untuk menyimpan pesan error jika ada
    const [error, setError] = useState(null);

    // useEffect akan berjalan saat komponen pertama kali di-mount
    useEffect(() => {
        // Fungsi untuk mengambil data dari backend
        const fetchActivities = async () => {
            try {
                const response = await axios.get(route("strava.activities"));
                setActivities(response.data); // Simpan data ke state
                setError(null);
            } catch (err) {
                setError("Failed to fetch activities from Strava.");
                console.error(err);
            } finally {
                setLoading(false); // Hentikan loading, baik berhasil maupun gagal
            }
        };

        fetchActivities();
    }, []); // Array kosong berarti efek ini hanya berjalan sekali

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <h2 className="font-semibold text-xl text-gray-800 leading-tight">
                    My Strava Activities
                </h2>
            }
        >
            <Head title="My Strava Activities" />

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                        {loading && <p>Loading your activities...</p>}
                        {error && <p className="text-red-500">{error}</p>}

                        {!loading && !error && (
                            <ul className="divide-y divide-gray-200">
                                {activities.length > 0 ? (
                                    activities.map((activity) => (
                                        <li key={activity.id} className="py-4">
                                            <h3 className="text-lg font-semibold">
                                                {activity.name}
                                            </h3>
                                            <p className="text-sm text-gray-600">
                                                {(
                                                    activity.distance / 1000
                                                ).toFixed(2)}{" "}
                                                km -{" "}
                                                {new Date(
                                                    activity.start_date
                                                ).toLocaleDateString()}
                                            </p>
                                        </li>
                                    ))
                                ) : (
                                    <p>No activities found.</p>
                                )}
                            </ul>
                        )}
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
