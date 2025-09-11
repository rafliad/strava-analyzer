import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
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
                                        <h3 className="text-lg font-semibold">{activity.name}</h3>
                                        <p className="text-sm text-gray-600">
                                            <span>‍🚴‍♀️ {(activity.distance / 1000).toFixed(2)} km</span>
                                            <span className="mx-2">|</span>
                                            <span>🕒 {new Date(activity.start_date).toLocaleDateString()}</span>
                                        </p>
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
