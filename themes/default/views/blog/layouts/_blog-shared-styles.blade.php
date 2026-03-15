<style>
.btn-read-more {
    display: inline-block;
    padding: 8px 20px;
    font-size: .85rem;
    font-weight: 500;
    color: #4a5568;
    background-color: transparent;
    border: 1px solid #d1d5db;
    border-radius: 4px;
    text-decoration: none;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    transition: all .2s ease;
}
.btn-read-more:hover,
.btn-read-more:focus,
.btn-read-more:active {
    text-decoration: none;
    color: #1a202c;
    border-color: #4a5568;
    background-color: #f7fafc;
}
.btn-read-more:visited {
    text-decoration: none;
    color: #4a5568;
}

/* Card symmetry */
.card-img-wrapper {
    position: relative;
    height: 220px;
    overflow: hidden;
    background-color: #f0f2f5;
    border-radius: .375rem .375rem 0 0;
}
.card-img-wrapper img,
.card-img-wrapper .card-img-top {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.card.h-100 {
    --bs-card-spacer-y: 0.5rem;
    --bs-card-spacer-x: 1rem;
    --tblr-card-spacer-y: 0.5rem;
    --tblr-card-spacer-x: 1rem;
    border-radius: .375rem;
    overflow: hidden;
}
.card.h-100 .card-body {
    padding: 8px 16px 14px !important;
}
.card.h-100 .card-title {
    margin-top: 0 !important;
}
.card-title-clamp {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    min-height: 2.6em;
    line-height: 1.3;
}
.card-excerpt-clamp {
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
    min-height: 3.9em;
    line-height: 1.3;
}
</style>
