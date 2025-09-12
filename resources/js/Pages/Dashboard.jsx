import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Head, Link } from "@inertiajs/react";

export default function Dashboard({ auth, stats, recentActivities }) {

    // Helper function to format distance
    const formatDistance = (distanceInMeters) => {
        return (distanceInMeters / 1000).toFixed(2);
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <h2 className="font-semibold text-xl text-gray-800 leading-tight">
                    Dashboard
                </h2>
            }
        >
            <Head title="Dashboard" />

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div className="p-6 text-gray-900">
                            Welcome back, {auth.user.name}!
                        </div>

                        {/* Bagian Koneksi Strava */}
                        <div className="p-6 border-t border-gray-200">
                            {stats.is_connected ? (
                                <div>
                                    <p className="text-green-600 font-semibold mb-4">
                                        You are connected to Strava.
                                    </p>
                                    <Link
                                        href={route("strava.disconnect")}
                                        method="post"
                                        as="button"
                                        className="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-500 active:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition ease-in-out duration-150"
                                    >
                                        Disconnect from Strava
                                    </Link>
                                </div>
                            ) : (
                                <div>
                                    <p className="mb-4">
                                        Connect your Strava account to see your stats and get AI-powered performance analysis.
                                    </p>
                                    <a
                                        href={route("strava.redirect")}
                                        className="inline-block px-4 py-2 bg-orange-500 text-white font-semibold rounded-lg shadow-md hover:bg-orange-700"
                                    >
                                        Connect with Strava
                                    </a>
                                </div>
                            )}
                        </div>

                        {/* Tampilkan statistik hanya jika terhubung */}
                        {stats.is_connected && (
                            <>
                                {/* Bagian Statistik */}
                                <div className="p-6 border-t border-gray-200">
                                    <h3 className="text-lg font-semibold mb-4">This Month's Stats</h3>
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div className="bg-gray-100 p-4 rounded-lg">
                                            <p className="text-sm text-gray-600">Total Distance</p>
                                            <p className="text-2xl font-bold">{formatDistance(stats.total_distance_this_month)} km</p>
                                        </div>
                                        <div className="bg-gray-100 p-4 rounded-lg">
                                            <p className="text-sm text-gray-600">Total Activities</p>
                                            <p className="text-2xl font-bold">{stats.total_activities_this_month}</p>
                                        </div>
                                    </div>
                                </div>

                                {/* Bagian Aktivitas Terbaru */}
                                <div className="p-6 border-t border-gray-200">
                                    <h3 className="text-lg font-semibold mb-4">Recent Activities</h3>
                                    <ul className="divide-y divide-gray-200">
                                        {recentActivities.length > 0 ? (
                                            recentActivities.map((activity) => (
                                                <li key={activity.id} className="py-3">
                                                    <p className="font-semibold">{activity.name}</p>
                                                    <p className="text-sm text-gray-500">
                                                        {formatDistance(activity.distance)} km - {new Date(activity.start_date).toLocaleDateString()}
                                                    </p>
                                                </li>
                                            ))
                                        ) : (
                                            <p>No recent activities found. Try syncing with Strava!</p>
                                        )}
                                    </ul>
                                </div>
                            </>
                        )}
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

