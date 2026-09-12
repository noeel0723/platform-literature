export default function SiteContainer({ as: Element = 'div', className = '', children, ...props }) {
    return (
        <Element className={`mx-auto w-full max-w-5xl px-5 sm:px-8 ${className}`} {...props}>
            {children}
        </Element>
    );
}
