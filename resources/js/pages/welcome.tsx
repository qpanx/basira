import { Head } from '@inertiajs/react';
import { useRef, useState } from 'react';
import { FamilyPreview } from '@/components/landing/family-preview';
import { Brand, Icon, type IconName } from '@/components/landing/icon';
import { ProductScene } from '@/components/landing/product-scene';
import { home } from '@/routes';
import { dashboard } from '@/routes/filament/admin/pages';
import { login } from '@/routes/filament/admin/auth';
import { switchMethod } from '@/routes/locale';
import type { LandingCopy } from '@/types/landing';

const navigation = ['features', 'how', 'family', 'pricing', 'faq'] as const;
const valueIcons: IconName[] = ['cane', 'scan', 'shield', 'heart'];
const featureIcons: IconName[] = ['scan', 'text', 'route', 'bell'];
const privacyIcons: IconName[] = ['pin', 'heart', 'eye', 'shield'];

function SectionHeading({
    eyebrow,
    title,
    description,
    centered = false,
}: {
    eyebrow: string;
    title: string;
    description?: string;
    centered?: boolean;
}) {
    return (
        <div
            className={
                centered
                    ? 'section-heading mx-auto text-center'
                    : 'section-heading'
            }
        >
            <p className="eyebrow">{eyebrow}</p>
            <h2>{title}</h2>
            {description && (
                <p className="section-description">{description}</p>
            )}
        </div>
    );
}

export default function Welcome({
    copy,
    locale,
    authenticated,
    year,
}: {
    copy: LandingCopy;
    locale: 'en' | 'ar';
    authenticated: boolean;
    year: number;
}) {
    const [menuOpen, setMenuOpen] = useState(false);
    const menuButton = useRef<HTMLButtonElement>(null);
    const closeMenu = () => setMenuOpen(false);
    const accountUrl = authenticated ? dashboard.url() : login.url();

    return (
        <div
            className="landing"
            dir={locale === 'ar' ? 'rtl' : 'ltr'}
            lang={locale}
            id="top"
        >
            <Head title={copy.meta.title}>
                <meta
                    head-key="description"
                    name="description"
                    content={copy.meta.description}
                />
                <meta
                    head-key="og:title"
                    property="og:title"
                    content={copy.meta.title}
                />
                <meta
                    head-key="og:description"
                    property="og:description"
                    content={copy.meta.description}
                />
                <meta property="og:type" content="website" />
                <meta
                    property="og:locale"
                    content={locale === 'ar' ? 'ar_AR' : 'en_US'}
                />
            </Head>
            <a href="#main" className="skip-link">
                {copy.nav.skip}
            </a>
            <header
                className="site-header"
                onKeyDown={(event) => {
                    if (event.key === 'Escape' && menuOpen) {
                        closeMenu();
                        menuButton.current?.focus();
                    }
                }}
            >
                <div className="page-width flex min-h-22 items-center justify-between gap-5">
                    <a href={home.url()} aria-label="BASIRA">
                        <Brand />
                    </a>
                    <nav
                        aria-label={copy.nav.label}
                        className="hidden items-center gap-7 text-sm font-medium lg:flex"
                    >
                        {navigation.map((id) => (
                            <a className="nav-link" key={id} href={`#${id}`}>
                                {copy.nav[id]}
                            </a>
                        ))}
                    </nav>
                    <div className="flex items-center gap-3 sm:gap-5">
                        <a
                            className="language-link"
                            href={switchMethod.url({
                                locale: locale === 'en' ? 'ar' : 'en',
                            })}
                            lang={locale === 'en' ? 'ar' : 'en'}
                            aria-label={copy.nav.language}
                        >
                            {locale === 'en' ? 'العربية' : 'English'}
                        </a>
                        <a
                            className="hidden text-sm font-medium sm:block"
                            href={accountUrl}
                        >
                            {authenticated
                                ? copy.nav.dashboard
                                : copy.nav.login}
                        </a>
                        <a
                            href="#how"
                            className="button button-dark hidden xl:inline-flex"
                        >
                            {copy.nav.explore}
                            <Icon name="arrow" className="direction-arrow" />
                        </a>
                        <button
                            ref={menuButton}
                            className="menu-button lg:hidden"
                            aria-label={
                                menuOpen ? copy.nav.close : copy.nav.menu
                            }
                            aria-expanded={menuOpen}
                            aria-controls="mobile-navigation"
                            onClick={() => setMenuOpen(!menuOpen)}
                        >
                            <Icon name={menuOpen ? 'close' : 'menu'} />
                        </button>
                    </div>
                </div>
                <nav
                    id="mobile-navigation"
                    aria-label={copy.nav.label}
                    hidden={!menuOpen}
                    className="mobile-navigation lg:hidden"
                >
                    {navigation.map((id) => (
                        <a key={id} href={`#${id}`} onClick={closeMenu}>
                            {copy.nav[id]}
                        </a>
                    ))}
                    <a href={accountUrl}>
                        {authenticated ? copy.nav.dashboard : copy.nav.login}
                    </a>
                </nav>
            </header>
            <main id="main" tabIndex={-1}>
                <section className="hero page-width">
                    <div className="hero-copy">
                        <p className="eyebrow">
                            <span className="status-dot" />
                            {copy.hero.eyebrow}
                        </p>
                        <h1>
                            {copy.hero.title}
                            <span>{copy.hero.accent}</span>
                        </h1>
                        <p className="hero-description">
                            {copy.hero.description}
                        </p>
                        <div className="mt-8 flex flex-wrap gap-3">
                            <a className="button button-teal" href="#how">
                                {copy.hero.primary}
                                <Icon
                                    name="arrow"
                                    className="direction-arrow"
                                />
                            </a>
                            <a
                                className="button button-outline"
                                href="#pricing"
                            >
                                {copy.hero.secondary}
                            </a>
                        </div>
                        <p className="hero-note">
                            <Icon name="shield" />
                            {copy.hero.note}
                        </p>
                    </div>
                    <ProductScene copy={copy} />
                </section>
                <section
                    className="value-strip page-width"
                    aria-label={copy.hero.note}
                >
                    <div className="grid gap-7 sm:grid-cols-2 lg:grid-cols-4">
                        {copy.values.map((value, index) => (
                            <div
                                key={value.title}
                                className="flex items-start gap-3"
                            >
                                <span className="value-icon">
                                    <Icon name={valueIcons[index]} />
                                </span>
                                <div>
                                    <h2 className="text-sm font-semibold">
                                        {value.title}
                                    </h2>
                                    <p className="mt-1.5 text-xs leading-relaxed text-slate-600">
                                        {value.description}
                                    </p>
                                </div>
                            </div>
                        ))}
                    </div>
                </section>
                <section id="how" className="section-space page-width">
                    <SectionHeading {...copy.how} centered />
                    <div className="steps-grid">
                        {copy.how.steps.map((step, index) => (
                            <article key={step.title}>
                                <div className="step-top">
                                    <span className="step-number" dir="ltr">
                                        0{index + 1}
                                    </span>
                                    <span className="step-line" />
                                </div>
                                <h3>{step.title}</h3>
                                <p>{step.description}</p>
                            </article>
                        ))}
                    </div>
                    <div className="connection-flow">
                        {copy.how.flow.map((label, index) => (
                            <div key={label} className="flow-item">
                                <span>
                                    <Icon
                                        name={
                                            (
                                                [
                                                    'cane',
                                                    'cloud',
                                                    'heart',
                                                ] as const
                                            )[index]
                                        }
                                    />
                                    {label}
                                </span>
                                {index < 2 && (
                                    <Icon
                                        name="arrow"
                                        className="direction-arrow flow-arrow"
                                    />
                                )}
                            </div>
                        ))}
                    </div>
                </section>
                <section
                    id="features"
                    className="feature-section section-space"
                >
                    <div className="page-width">
                        <SectionHeading {...copy.features} />
                        <div className="feature-grid">
                            {copy.features.items.map((feature, index) => (
                                <article
                                    className={`feature-card feature-${index}`}
                                    key={feature.label}
                                >
                                    <div
                                        className="feature-visual"
                                        aria-hidden="true"
                                    >
                                        <span className="visual-grid" />
                                        <span className="feature-large-icon">
                                            <Icon name={featureIcons[index]} />
                                        </span>
                                        <span className="visual-caption">
                                            {feature.visual}
                                        </span>
                                        {index === 1 && (
                                            <div className="text-lines">
                                                <i />
                                                <i />
                                                <i />
                                            </div>
                                        )}
                                    </div>
                                    <div className="feature-card-copy">
                                        <p className="eyebrow">
                                            {feature.label}
                                        </p>
                                        <h3>{feature.title}</h3>
                                        <p>{feature.description}</p>
                                    </div>
                                </article>
                            ))}
                        </div>
                        <p className="mt-7 max-w-3xl text-sm leading-relaxed text-slate-600">
                            {copy.features.note}
                        </p>
                    </div>
                </section>
                <section
                    id="family"
                    className="section-space page-width family-grid"
                >
                    <div>
                        <SectionHeading {...copy.family} />
                        <ul className="mt-8 space-y-5">
                            {copy.family.points.map((point) => (
                                <li
                                    key={point}
                                    className="flex items-start gap-3 text-sm leading-relaxed text-slate-600"
                                >
                                    <Icon
                                        name="check"
                                        className="shrink-0 text-teal-700"
                                    />
                                    {point}
                                </li>
                            ))}
                        </ul>
                        <a
                            className="mt-8 inline-flex min-h-11 items-center gap-3 font-semibold text-teal-800"
                            href="#pricing"
                        >
                            {copy.family.link}
                            <Icon name="arrow" className="direction-arrow" />
                        </a>
                    </div>
                    <FamilyPreview copy={copy.family} />
                </section>
                <section id="pricing" className="pricing-section section-space">
                    <div className="page-width">
                        <SectionHeading {...copy.pricing} centered />
                        <div className="pricing-grid">
                            {copy.pricing.plans.map((plan, index) => (
                                <article
                                    key={plan.name}
                                    className={`plan-card ${index === 1 ? 'featured-plan' : ''}`}
                                    aria-labelledby={`plan-${index}`}
                                >
                                    <div className="plan-banner">
                                        {index === 1
                                            ? copy.pricing.highlight
                                            : copy.pricing.planned}
                                    </div>
                                    <div className="plan-content">
                                        <h3
                                            id={`plan-${index}`}
                                            className="text-sm font-semibold tracking-wider"
                                        >
                                            {plan.name}
                                        </h3>
                                        <p className="mt-3 text-sm text-slate-600">
                                            {plan.tagline}
                                        </p>
                                        <p className="my-7 text-3xl font-semibold tracking-tight">
                                            {copy.pricing.soon}
                                        </p>
                                        <a
                                            href="#plan-availability"
                                            className={`button w-full ${index === 1 ? 'button-teal' : 'button-outline'}`}
                                            aria-label={`${copy.pricing.cta} — ${plan.name}`}
                                        >
                                            {copy.pricing.cta}
                                            <Icon
                                                name="arrow"
                                                className="direction-arrow"
                                            />
                                        </a>
                                        <p className="plan-includes">
                                            {index === 1
                                                ? copy.pricing.includesFree
                                                : index === 2
                                                  ? copy.pricing.includesPlus
                                                  : copy.pricing.planned}
                                        </p>
                                        <ul className="space-y-4">
                                            {plan.features.map((feature) => (
                                                <li
                                                    className="flex items-start gap-2.5 text-sm leading-relaxed"
                                                    key={feature}
                                                >
                                                    <Icon
                                                        name="check"
                                                        className="size-4 shrink-0 translate-y-0.5 text-teal-700"
                                                    />
                                                    {feature}
                                                </li>
                                            ))}
                                        </ul>
                                    </div>
                                </article>
                            ))}
                        </div>
                        <div
                            id="plan-availability"
                            tabIndex={-1}
                            className="plan-availability"
                        >
                            <p className="font-semibold">
                                {copy.pricing.notice}
                            </p>
                            <p>{copy.pricing.existing}</p>
                            <p>
                                <span className="font-semibold">
                                    {copy.pricing.planned}:{' '}
                                </span>
                                {copy.pricing.future}
                            </p>
                        </div>
                    </div>
                </section>
                <section className="section-space page-width">
                    <SectionHeading {...copy.privacy} centered />
                    <div className="privacy-grid">
                        {copy.privacy.items.map((item, index) => (
                            <article key={item.title}>
                                <span className="privacy-icon">
                                    <Icon name={privacyIcons[index]} />
                                </span>
                                <h3>{item.title}</h3>
                                <p>{item.description}</p>
                            </article>
                        ))}
                    </div>
                </section>
                <section
                    id="faq"
                    className="faq-section section-space page-width"
                >
                    <SectionHeading
                        eyebrow={copy.faq.eyebrow}
                        title={copy.faq.title}
                    />
                    <div className="faq-list">
                        {copy.faq.items.map((item) => (
                            <details key={item.question}>
                                <summary>
                                    {item.question}
                                    <Icon name="plus" />
                                </summary>
                                <p>{item.answer}</p>
                            </details>
                        ))}
                    </div>
                </section>
                <section className="final-cta page-width">
                    <div className="cta-orbit" aria-hidden="true" />
                    <p className="eyebrow">{copy.final.eyebrow}</p>
                    <h2>{copy.final.title}</h2>
                    <a className="button button-light" href="#how">
                        {copy.final.cta}
                        <Icon name="arrow" className="direction-arrow" />
                    </a>
                </section>
            </main>
            <footer className="page-width site-footer">
                <div className="footer-top">
                    <div>
                        <a href="#top" aria-label={copy.footer.back}>
                            <Brand />
                        </a>
                        <p className="mt-5 max-w-xs text-sm leading-relaxed text-slate-600">
                            {copy.footer.description}
                        </p>
                    </div>
                    <nav
                        aria-label={copy.nav.label}
                        className="grid grid-cols-2 gap-x-10 gap-y-4 text-sm"
                    >
                        {navigation.map((id) => (
                            <a key={id} href={`#${id}`}>
                                {copy.nav[id]}
                            </a>
                        ))}
                        <a href="#contact">{copy.footer.contact}</a>
                    </nav>
                    <div id="contact" tabIndex={-1}>
                        <h2 className="text-sm font-semibold">
                            {copy.footer.contact}
                        </h2>
                        <p className="mt-3 max-w-56 text-sm leading-relaxed text-slate-600">
                            {copy.footer.contactSoon}
                        </p>
                        <p className="mt-4 max-w-56 text-xs leading-relaxed text-slate-600">
                            {copy.footer.legal}
                        </p>
                    </div>
                </div>
                <div className="footer-bottom">
                    <p>
                        © {year} {copy.footer.rights}
                    </p>
                    <p>{copy.footer.note}</p>
                </div>
            </footer>
        </div>
    );
}
