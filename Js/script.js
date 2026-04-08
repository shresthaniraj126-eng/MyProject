let body=document.querySelector('body');

let textcontainer = document.querySelector('.container_texts');    
let imagecontainer = document.querySelector('.container_images');

const texts=[{title:'Buzz Cut '},{title:'Fade Cut'},{title:'Two Block'},{title:'Crew Cut'},{title:'Brush Cut'},{title:'Mohawk'},{title:'Quiff'},{title:'Pompadour'},{title:'Flat Top'},{title:'Caesar Cut'},{title:'French Crop'},{title:'Ivy League'},{title:'Comb Over'},{title:'Side Part'},{title:'Taper Fade'}];
const images=['images/image0.webp','images/image1.jpg','images/image01.webp','images/image2.jpg','images/image02.webp','images/image03.webp','images/image13.jpg','images/image14.webp','images/image15.webp'];

texts_length=texts.length;
images_length=images.length;

body.style.setProperty('--texts_length',texts_length);
body.style.setProperty('--images_length',images_length);

// arr is the array of objects, typeOfElement is the type of element to be created for each item (string), 
// typeOfContent is the type of element to hold the content(string), 
// className is the class name to be added to each item(string), 
// appendParent is the parent element to which the items will be appended
    
function createItems_TextImage(arr,typeOfElement,typeOfContent,className,appendParent){ arr.forEach((element,i)=>{
        let item= document.createElement(typeOfElement);
        item.classList.add(className);
        item.style.setProperty('--position',i);
        let content=document.createElement(typeOfContent);
        if(typeOfContent==='img'){
            content.src=element;
        }else{
            content.textContent=element.title;
        }
        item.appendChild(content);
        appendParent.appendChild(item);
    });
}

createItems_TextImage(texts,'div','p','item_text',textcontainer);

createItems_TextImage(images,'div','img','item_image',imagecontainer);