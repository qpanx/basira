import { useState } from 'react';
import type { LandingCopy } from '@/types/landing';
import { Brand, Icon } from './icon';

type DeviceState = 'last' | 'live' | 'offline';

export function MapPreview() {
    return (
        <div className="demo-map" aria-hidden="true">
            <div className="map-park" />
            <div className="map-block block-one" />
            <div className="map-block block-two" />
            <div className="map-block block-three" />
            <div className="map-road road-one" />
            <div className="map-road road-two" />
            <div className="map-road road-three" />
            <div className="map-route" />
            <span className="map-marker">
                <Icon name="pin" />
            </span>
        </div>
    );
}

export function FamilyPreview({ copy }: { copy: LandingCopy['family'] }) {
    const [state, setState] = useState<DeviceState>('last');
    const time =
        state === 'live'
            ? copy.liveTime
            : state === 'offline'
              ? copy.offlineTime
              : copy.lastTime;
    const note =
        state === 'live'
            ? copy.fresh
            : state === 'offline'
              ? copy.offlineNote
              : copy.stale;

    return (
        <div className="family-preview">
            <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 p-5">
                <Brand />
                <span className="text-xs font-medium text-slate-600">
                    {copy.dashboard}
                </span>
            </div>
            <div className="p-5 sm:p-7">
                <p className="mb-5 text-xs font-medium text-slate-600">
                    {copy.demo}
                </p>
                <div className="mb-5 flex items-center gap-3">
                    <span className="flex size-11 items-center justify-center rounded-full bg-teal-100 font-semibold text-teal-900">
                        {copy.initial}
                    </span>
                    <div>
                        <h3 className="font-semibold">{copy.person}</h3>
                        <p className="text-xs text-slate-600">
                            {copy.relationship}: {copy.contact}
                        </p>
                    </div>
                </div>
                <div className="mb-4 grid grid-cols-2 gap-3">
                    <div className="status-tile">
                        <span>{copy.battery}</span>
                        <strong>
                            <span className="battery-icon" aria-hidden="true" />
                            {copy.batteryValue}
                        </strong>
                    </div>
                    <div className="status-tile">
                        <span>{copy.status}</span>
                        <strong
                            className={
                                state === 'offline'
                                    ? 'text-amber-800'
                                    : 'text-teal-800'
                            }
                        >
                            <i
                                className={
                                    state === 'offline'
                                        ? 'status-dot offline'
                                        : 'status-dot'
                                }
                            />
                            {state === 'offline'
                                ? copy.offline
                                : copy.connected}
                        </strong>
                    </div>
                </div>
                <div
                    role="group"
                    aria-label={copy.states}
                    className="state-switch"
                >
                    {(['last', 'live', 'offline'] as const).map((value) => (
                        <button
                            key={value}
                            type="button"
                            aria-pressed={state === value}
                            onClick={() => setState(value)}
                        >
                            {copy[value]}
                        </button>
                    ))}
                </div>
                <div className="overflow-hidden rounded-xl border border-slate-200">
                    <MapPreview />
                    <div
                        className="bg-white p-4"
                        aria-live="polite"
                        aria-atomic="true"
                    >
                        <p className="text-sm font-semibold">
                            {state === 'live' ? copy.live : copy.last}
                        </p>
                        <p className="mt-1 text-sm text-slate-600">
                            {copy.location}
                        </p>
                        <p className="mt-2 text-xs font-medium text-teal-800">
                            {time}
                        </p>
                        <p className="mt-1 text-xs leading-relaxed text-slate-600">
                            {note}
                        </p>
                    </div>
                </div>
                <div className="mt-4 flex items-start gap-3 rounded-xl border border-rose-100 bg-rose-50 p-4">
                    <Icon name="bell" className="shrink-0 text-rose-700" />
                    <div>
                        <p className="text-sm font-semibold text-rose-950">
                            {copy.sos}
                        </p>
                        <p className="mt-1 text-xs leading-relaxed text-rose-900">
                            {copy.sosDetail}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    );
}
