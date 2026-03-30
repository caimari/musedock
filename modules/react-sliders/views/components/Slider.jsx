import React from 'react';
import { Swiper, SwiperSlide } from 'swiper/react';
import { Navigation, Pagination, Autoplay } from 'swiper/modules';

// Import Swiper styles
import 'swiper/css';
import 'swiper/css/navigation';
import 'swiper/css/pagination';

export default function ReactSlider({ slides, settings }) {
  return (
    <Swiper
      modules={[Navigation, Pagination, Autoplay]}
      navigation={settings.navigation}
      pagination={{ clickable: settings.pagination }}
      autoplay={settings.autoplay ? { delay: settings.autoplay_delay } : false}
      loop={settings.loop}
      slidesPerView={settings.slides_per_view}
      spaceBetween={settings.space_between}
      speed={settings.speed}
      className="w-full h-full"
    >
      {slides.map((slide) => (
        <SwiperSlide key={slide.id}>
          <div className="relative h-full" style={{ backgroundColor: slide.styles.backgroundColor }}>
            <img src={slide.image} alt={slide.title} className="w-full h-full object-cover" />
            <div
              className="absolute inset-0 bg-black"
              style={{ opacity: slide.styles.overlayOpacity }}
            ></div>
            <div className="absolute inset-0 flex flex-col justify-center items-center text-center p-8" style={{ color: slide.styles.color }}>
              {slide.title && <h2 className="text-4xl md:text-6xl font-bold mb-4">{slide.title}</h2>}
              {slide.subtitle && <h3 className="text-2xl md:text-3xl mb-4">{slide.subtitle}</h3>}
              {slide.description && <p className="text-lg md:text-xl mb-8 max-w-2xl">{slide.description}</p>}
              {slide.button.text && (
                <a
                  href={slide.button.link}
                  target={slide.button.target}
                  className="px-8 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition"
                >
                  {slide.button.text}
                </a>
              )}
            </div>
          </div>
        </SwiperSlide>
      ))}
    </Swiper>
  );
}
