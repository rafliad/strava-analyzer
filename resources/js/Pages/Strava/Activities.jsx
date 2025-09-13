import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, Link } from '@inertiajs/react';
import { useState } from 'react';

export default function Activities({ auth, activities }) {
    
    const [isSyncing, setIsSyncing] = useState(false);
    const handleSync = () => {
        router.post(route('strava.sync'), {}, {
            onStart: () => setIsSyncing(true),
            onFinish: () => setIsSyncing(false),
            preserveScroll: true,
        });
    };

    const formatTime = (seconds) => {
        const h = Math.floor(seconds / 3600).toString().padStart(2, '0');
        const m = Math.floor((seconds % 3600) / 60).toString().padStart(2, '0');
        const s = (seconds % 60).toString().padStart(2, '0');
        return `${h}:${m}:${s}`;
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex justify-between items-center">
                    <h2 className="font-semibold text-xl text-gray-800 leading-tight">
                        My Strava Activities
                    </h2>
                    <button
                        onClick={handleSync}
                        disabled={isSyncing}
                        className="px-4 py-2 bg-orange-500 text-white font-semibold rounded-lg shadow-md hover:bg-orange-700 disabled:bg-orange-300 disabled:cursor-not-allowed"
                    >
                        {isSyncing ? 'Syncing...' : 'Sync with Strava'}
                    </button>
                </div>
            }
        >
            <Head title="My Strava Activities" />

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">

                        <ul className="divide-y divide-gray-200">
                            {activities.length > 0 ? (
                                activities.map((activity) => (
                                    <li key={activity.id} className="py-4">
                                        <Link href={route('activities.show', activity.id)} className="block hover:bg-gray-50 p-2 rounded-lg">
                                                <div className="flex justify-between items-center">
                                                    <div>
                                                        <h3 className="text-lg font-semibold text-indigo-600">{activity.name}</h3>
                                                        <p className="text-sm text-gray-600 mt-1">
                                                            <span className="font-medium">{(activity.distance / 1000).toFixed(2)} km</span>
                                                            <span className="mx-2">|</span>
                                                            <span>{formatTime(activity.moving_time)}</span>
                                                            <span className="mx-2">|</span>
                                                            <span>{new Date(activity.start_date).toLocaleDateString()}</span>
                                                        </p>
                                                    </div>
                                                    <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5 text-gray-400" viewBox="0 0 20 20" fill="currentColor">
                                                        <path fillRule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clipRule="evenodd" />
                                                    </svg>
                                                </div>
                                            </Link>
                                    </li>
                                ))
                            ) : (
                                <p>No activities found. Try syncing with Strava!</p>
                            )}
                        </ul>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
