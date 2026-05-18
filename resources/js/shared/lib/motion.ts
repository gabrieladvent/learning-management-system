import type { Variants, Transition } from 'framer-motion';

const ease: Transition['ease'] = [0.22, 1, 0.36, 1];

export const fadeUp: Variants = {
    hidden: { opacity: 0, y: 12 },
    visible: {
        opacity: 1,
        y: 0,
        transition: { duration: 0.35, ease },
    },
};

export const fadeIn: Variants = {
    hidden: { opacity: 0 },
    visible: {
        opacity: 1,
        transition: { duration: 0.25, ease },
    },
};

export const staggerContainer: Variants = {
    hidden: { opacity: 1 },
    visible: {
        opacity: 1,
        transition: {
            staggerChildren: 0.06,
            delayChildren: 0.05,
        },
    },
};

export const cardHover = {
    rest: { y: 0 },
    hover: {
        y: -3,
        transition: { duration: 0.2, ease },
    },
};

export const pageTransition = {
    initial: { opacity: 0, y: 8 },
    animate: { opacity: 1, y: 0 },
    exit: { opacity: 0, y: -8 },
    transition: { duration: 0.25, ease },
};
