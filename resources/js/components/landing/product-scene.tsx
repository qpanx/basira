import type { LandingCopy } from '@/types/landing';
import { Icon } from './icon';
import { MapPreview } from './family-preview';

export function ProductScene({ copy }: { copy: LandingCopy }) {
    return (
        <figure className="product-figure">
            <div className="product-scene" aria-hidden="true">
                <div className="orbit orbit-one" />
                <div className="orbit orbit-two" />
                <span className="scene-coordinate">B / 01</span>
                <div className="cane-shadow" />
                <div className="smart-cane">
                    <div className="cane-handle">
                        <span className="cane-sensor" />
                        <span className="cane-light" />
                    </div>
                    <div className="cane-shaft" />
                    <div className="cane-band" />
                    <div className="cane-tip" />
                </div>
                <div className="concept-phone">
                    <div className="phone-notch" />
                    <div className="phone-content">
                        <p
                            className="text-[10px] font-bold tracking-[0.2em]"
                            dir="ltr"
                        >
                            BASIRA
                        </p>
                        <p className="mt-5 text-[11px] text-slate-600">
                            {copy.hero.phone}
                        </p>
                        <p className="mt-1 text-lg leading-snug font-semibold">
                            {copy.hero.phoneStatus}
                        </p>
                        <div className="my-5">
                            <MapPreview />
                        </div>
                        <div className="flex items-center gap-2 text-xs font-semibold text-teal-800">
                            <Icon name="route" />
                            {copy.hero.phoneLabel}
                        </div>
                        <p className="mt-2 text-[10px] text-slate-600">
                            {copy.hero.phoneHint}
                        </p>
                        <div className="mt-5 flex items-center gap-2 rounded-lg bg-rose-50 p-3 text-[11px] font-semibold text-rose-800">
                            <Icon name="bell" />
                            {copy.hero.sos}
                        </div>
                    </div>
                </div>
                <div className="scene-chip assistance-chip">
                    <span className="chip-icon">
                        <Icon name="scan" />
                    </span>
                    <div>
                        <strong>{copy.hero.assist}</strong>
                        <span>{copy.hero.assistDetail}</span>
                    </div>
                </div>
                <div className="scene-chip connection-chip">
                    <span className="chip-icon">
                        <Icon name="heart" />
                    </span>
                    <div>
                        <strong>{copy.hero.connected}</strong>
                        <span>{copy.family.contact}</span>
                    </div>
                    <span className="status-dot" />
                </div>
                <div className="scene-dashboard">
                    <div className="flex items-center justify-between gap-3 border-b border-slate-100 pb-3">
                        <strong>{copy.family.dashboard}</strong>
                        <Icon name="eye" />
                    </div>
                    <div className="mt-3 flex items-center justify-between gap-3">
                        <span>{copy.family.battery}</span>
                        <strong className="text-teal-800">
                            {copy.family.batteryValue}
                        </strong>
                    </div>
                    <div className="mt-3 h-1.5 rounded bg-teal-50">
                        <div className="h-full w-3/4 rounded bg-teal-600" />
                    </div>
                    <p className="mt-3 text-slate-600">{copy.family.last}</p>
                    <p className="mt-1 text-[10px] text-slate-600">
                        {copy.family.lastTime}
                    </p>
                </div>
            </div>
            <figcaption className="mt-4 text-center text-xs leading-relaxed text-slate-600">
                {copy.hero.concept}
            </figcaption>
        </figure>
    );
}
